<?php

namespace Tuna\TunaGateway\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface; // Add this import
use Tuna\TunaGateway\Helper\MarketplaceSplitResolver;

class CreateTunaOrder implements ObserverInterface
{
    protected $_scopeConfig;
    protected $_tunaEndpointDomain;
    protected $_code = "tuna";
    protected $_coreSession;
    protected $invoiceService;
    protected $transaction;
    protected $invoiceSender;
    protected $curlFactory;
    protected $_checkoutSession;
    protected $_session;
    protected $jsonHelper;
    protected $curlClient;
    protected $storeManager; // Add property for StoreManagerInterface
    protected $marketplaceSplitResolver;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Framework\Serialize\Serializer\Json $jsonHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        CoreSession $coreSession,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        Curl $curlClient,
        StoreManagerInterface $storeManager, // Inject StoreManagerInterface
        MarketplaceSplitResolver $marketplaceSplitResolver
    ) {
        $this->_scopeConfig = $scopeConfigInterface;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        if ($this->_scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') == 'production') {
            $this->_tunaEndpointDomain = 'engine.tunagateway.com';
        } else {
            $this->_tunaEndpointDomain = 'sandbox.tuna-demo.uy';
        }
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->_coreSession = $coreSession;
        $this->_checkoutSession = $checkoutSession;
        $this->curlClient = $curlClient;
        $this->storeManager = $storeManager; // Assign StoreManagerInterface
        $this->marketplaceSplitResolver = $marketplaceSplitResolver;
    }

    function roundDown($decimal, $precision)
    {
        $sign = $decimal > 0 ? 1 : -1;
        $base = pow(10, $precision);
        return floor(abs($decimal) * $base) / $base * $sign;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();
        $paymentMethodCode = strtolower((string)($payment ? $payment->getMethod() : ''));
        $supportedMethods = ['tuna', 'credit', 'boleto', 'pix', 'crypto', 'link', 'googlepay', 'applepay'];

        // Process only Tuna methods. Relying on a specific initial order status is brittle across environments.
        if (in_array($paymentMethodCode, $supportedMethods, true)) {
            $orderId = $order->getIncrementId();
            $shipping = $order->getShippingAddress();
            $billing = $order->getBillingAddress();
            $paymentAdditionalInfo = $payment->getAdditionalInformation();
            $configuredAppKey = (string)$this->_scopeConfig->getValue('payment/tuna_payment/credentials/appKey');
            $engineAppToken = $this->extractGuid($configuredAppKey);

            $tokenSessionParam = $paymentAdditionalInfo["session_id"] ?? null;
            $buyerDocument = $paymentAdditionalInfo["buyer_document"] ?? "";
            $isBoletoPayment = ($paymentAdditionalInfo["is_boleto_payment"] ?? "false") == "true";
            $isCryptoPayment = ($paymentAdditionalInfo["is_crypto_payment"] ?? "false") == "true";
            $isPixPayment = ($paymentAdditionalInfo["is_pix_payment"] ?? "false") == "true";
            $isLinkPayment = ($paymentAdditionalInfo["is_link_payment"] ?? "false") == "true";
            $paymentType = strtoupper((string)($paymentAdditionalInfo["payment_type"] ?? ''));

            // Prefer Magento payment method code over additional_data flags to avoid stale state.
            switch ($paymentMethodCode) {
                case 'credit':
                    $isBoletoPayment = false;
                    $isCryptoPayment = false;
                    $isPixPayment = false;
                    $isLinkPayment = false;
                    break;
                case 'boleto':
                    $isBoletoPayment = true;
                    $isCryptoPayment = false;
                    $isPixPayment = false;
                    $isLinkPayment = false;
                    break;
                case 'crypto':
                    $isBoletoPayment = false;
                    $isCryptoPayment = true;
                    $isPixPayment = false;
                    $isLinkPayment = false;
                    break;
                case 'pix':
                    $isBoletoPayment = false;
                    $isCryptoPayment = false;
                    $isPixPayment = true;
                    $isLinkPayment = false;
                    break;
                case 'link':
                    $isBoletoPayment = false;
                    $isCryptoPayment = false;
                    $isPixPayment = false;
                    $isLinkPayment = true;
                    break;
            }
            $isGooglePayPayment = ($paymentAdditionalInfo["is_googlepay_payment"] ?? "false") == "true"
                || $paymentMethodCode === 'googlepay'
                || $paymentType === 'TUNA_EGOOGLEPAY';
            $isApplePayPayment = ($paymentAdditionalInfo["is_applepay_payment"] ?? "false") == "true"
                || $paymentMethodCode === 'applepay'
                || $paymentType === 'TUNA_EAPPLEPAY';
            $walletTokenProvider = $paymentAdditionalInfo["wallet_token_provider"] ?? '';
            $walletToken = $paymentAdditionalInfo["wallet_token"] ?? '';
            $isWalletPayment = $isGooglePayPayment || $isApplePayPayment;

            if ($isWalletPayment && empty($walletTokenProvider)) {
                $walletTokenProvider = $isGooglePayPayment ? 'GooglePay' : 'ApplePay';
            }

            $fullName =  $billing["firstname"] . " " . $billing["lastname"];
            $this->_coreSession->start();
            $customerID = $this->_coreSession->getCostumerID();
            $isNewCustomer = !$order->getCustomerId();
            if (!$isNewCustomer) {
                $customerID = $order->getCustomerId();
            }
            $itens = [];

            $creditCardData = json_decode($paymentAdditionalInfo["credit_card_data"] ?? "[]");
            $valorTotal = $this->getValorFinal($order->getGrandTotal());
            $juros = 1;
            if ($creditCardData != null && count($creditCardData) > 0) {

                if (count($creditCardData) > 2) {
                    $order->setStatus('tuna_Cancelled');
                    $order->addStatusHistoryComment('Não é permitida a compra com mais de 2 cartões');
                    $order->setGrandTotal($valorTotal);
                    $order->save();
                    return;
                }
                if ($this->getValorFinal($order->getGrandTotal()) > $this->getValorFinal($creditCardData[0]->credit_card_amount)+$this->getValorFinal($creditCardData[1]->credit_card_amount ?? 0)) {
                    $order->setStatus('tuna_Cancelled');
                    $order->addStatusHistoryComment('Dados invalidos para compra: R$ '.($this->getValorFinal($creditCardData[0]->credit_card_amount)+$this->getValorFinal($creditCardData[1]->credit_card_amount ?? 0)));
                    $order->setGrandTotal($valorTotal);
                    $order->save();
                    return;
                }
                $valorTotalComJuros = $this->getValorFinal($creditCardData[0]->credit_card_amount, $creditCardData[0]->credit_card_installments) +
                    $this->getValorFinal($creditCardData[1]->credit_card_amount ?? 0, $creditCardData[1]->credit_card_installments ?? 1);

                $juros = $valorTotalComJuros / $valorTotal;
                $valorTotal = round($valorTotalComJuros, 2);
            }
            $shippingValue = 0;
            try{
                $shippingValue = $order->getBaseShippingAmount();
            } catch (\Exception $e) {}
            $itemsCollection = $order->getAllVisibleItems();
            $shippingAmountPerItem =  ($shippingValue * $juros) / count($itemsCollection);
            $fullDiscountAmountPerItem =  ($order->getDiscountAmount() * $juros) / count($itemsCollection);
            foreach ($itemsCollection as $item) {
                $qtdFloor = ceil($item->getQtyToInvoice());
                $valorItem = (($item->getPrice()) * $juros) + ($shippingAmountPerItem / $qtdFloor);
                $percentualDiscount = ($valorItem*$qtdFloor)/($valorTotal-($order->getDiscountAmount() * $juros));
                $valorItem = $this->roundDown(($valorItem +((($order->getDiscountAmount() * $juros)/$qtdFloor)*$percentualDiscount)),2);
                
                $itemData = [
                    "Amount" =>  $valorItem,
                    "ProductDescription" => $item->getProduct()->getName(),
                    "ItemQuantity" => $qtdFloor,
                    "CategoryName" => $item->getProductType(),
                    "AntiFraud" => [
                        "Ean" => $item->getSku()
                    ]
                ];
                
                // Adicionar split se configuração estiver ativa
                $splitData = $this->marketplaceSplitResolver->getSplitForItem($item);
                if ($splitData) {
                    $itemData['split'] = $splitData;
                }
                
                $cItem = [$itemData];
                $itens = array_merge($itens, $cItem);
            }

            $deliveryAddress = null;
            if (!empty($shipping) && isset($shipping["street"]) && isset($shipping["city"]) && isset($shipping["region"]) && isset($shipping["postcode"]) && isset($shipping["telephone"])) {
                $address = preg_split("/(\r\n|\n|\r)/", $shipping["street"]);
                $number = "";
                if (sizeof($address) > 1) {
                    $number = $address[1];
                }
                $complement = "";
                if (sizeof($address) > 2) {
                    $complement = $address[2];
                }
                $deliveryAddress = [
                    "Street" => $address[0],
                    "Number" => $number,
                    "Complement" => $complement,
                    "Neighborhood" => "",
                    "City" => $shipping["city"],
                    "State" => $this->getStateCode($shipping["region"]),
                    "Country" => $shipping["country_id"] != null ? $shipping["country_id"] : "BR",
                    "PostalCode" => $shipping["postcode"],
                    "Phone" => $shipping["telephone"]
                ];
            }
            $numberB = "1";
            $complementB = "";
            $billingAddress = "";
            if (!empty($billing) && isset($billing["street"])) {
                $addressB  = preg_split("/(\r\n|\n|\r)/", $billing["street"]);
                $billingAddress = $addressB[0];
                if (sizeof($addressB) > 1) {
                    $numberB = $addressB[1];
                }
                if (sizeof($addressB) > 2) {
                    $complementB = $addressB[2];
                }
            }
            $documentType = "CPF";
            if (strlen($buyerDocument) > 17) {
                $documentType = "CNPJ";
            }
            $paymentMethods = [];
            if (!$isBoletoPayment) {
                if (
                    !$isPixPayment
                    && !$isCryptoPayment
                    && !$isLinkPayment
                ) {
                    if ($isWalletPayment) {
                        if (empty($walletTokenProvider)) {
                            $walletTokenProvider = $isGooglePayPayment ? 'GooglePay' : 'ApplePay';
                        }

                        if (empty($walletToken)) {
                            $order->setStatus('tuna_Cancelled');
                            $order->addStatusHistoryComment('Token de carteira digital ausente para processamento de pagamento.');
                            $order->save();
                            $this->_checkoutSession->restoreQuote();
                            throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Não foi possível processar o token da carteira digital.'));
                        }

                        $payment = $order->getPayment();
                        $payment->setMethod($isGooglePayPayment ? 'googlepay' : 'applepay');
                        $payment->save();
                        $order->addStatusHistoryComment($isGooglePayPayment ? 'Pagamento em Google Pay' : 'Pagamento em Apple Pay');
                        $paymentMethods = [
                            [
                                "PaymentMethodType" => "1",
                                "Amount" => $valorTotal,
                                "Installments" => 1,
                                "CardInfo" => [
                                    "TokenProvider" => $walletTokenProvider,
                                    "Token" => $walletToken,
                                ]
                            ]
                        ];
                    } else {
                        if (empty($creditCardData) || count((array)$creditCardData) === 0) {
                            $order->setStatus('tuna_Cancelled');
                            $order->addStatusHistoryComment('Dados de cartao ausentes para o fluxo de credito.');
                            $order->save();
                            $this->_checkoutSession->restoreQuote();
                            throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operacao. Nao foi possivel identificar os dados de pagamento.'));
                        }

                        $order->addStatusHistoryComment('Pagamento em Cartão de crédito');
                        $payment = $order->getPayment();
                        $payment->setMethod('credit');
                        $payment->save();

                        foreach ((array)$creditCardData as $index => $creditCard) {
                            if ($index == 0) {
                                $creditCardAmount = round($this->getValorFinal($creditCard->credit_card_amount, $creditCard->credit_card_installments), 2);
                            } else {
                                $creditCardAmount = $valorTotal - round($this->getValorFinal($creditCardData[0]->credit_card_amount, $creditCardData[0]->credit_card_installments), 2);
                            }

                            $cardHolderName = isset($creditCard->card_holder_name) ? $creditCard->card_holder_name : 'Unknown';

                            $expirationMonth = isset($creditCard->credit_card_expiration_month) ? $creditCard->credit_card_expiration_month * 1 : null;
                            $expirationYear = isset($creditCard->credit_card_expiration_year) ? $creditCard->credit_card_expiration_year * 1 : null;

                            $creditCardToken = $creditCard->credit_card_token ?? '';
                            if (empty($creditCardToken)) {
                                $order->setStatus('tuna_Cancelled');
                                $order->addStatusHistoryComment('Token de cartao ausente para processamento de pagamento.');
                                $order->save();
                                $this->_checkoutSession->restoreQuote();
                                throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operacao. Nao foi possivel identificar o token do cartao.'));
                            }

                            $brandName = $creditCard->credit_card_brand ?? 'Unknown';

                            $paymentMethod = [
                                "PaymentMethodType" => "1",
                                "amount" => $creditCardAmount,
                                "Installments" => $creditCard->credit_card_installments,
                                "CardInfo" => [
                                    "TokenProvider" => "Tuna",
                                    "CardNumber" => "",
                                    "CardHolderName" => $cardHolderName,
                                    "BrandName" => $brandName,
                                    "ExpirationMonth" => $expirationMonth,
                                    "ExpirationYear" => $expirationYear,
                                    "Token" => $creditCardToken,
                                    "TokenSingleUse" => $isNewCustomer ? 1 : 0,
                                    "SaveCard" => false,
                                    "BillingInfo" => [
                                        "Document" => $buyerDocument,
                                        "DocumentType" => $documentType,
                                        "Address" => [
                                            "Street" => $billingAddress,
                                            "Number" => $numberB,
                                            "Complement" => $complementB,
                                            "Neighborhood" => "Centro",
                                            "City" => $billing["city"],
                                            "State" => $this->getStateCode($billing["region"]),
                                            "Country" => $billing["country_id"] != null ? $billing["country_id"] : "BR",
                                            "PostalCode" => $billing["postcode"],
                                            "Phone" => $billing["telephone"]
                                        ]
                                    ]
                                ]
                            ];
                            $paymentMethods[] = $paymentMethod;
                        }
                    }
                } elseif ($isCryptoPayment) {
                    $payment = $order->getPayment();
                    $payment->setMethod('crypto');
                    $payment->save();
                    $order->addStatusHistoryComment('Pagamento em Bitcoin');
                    $paymentMethods = [
                        [
                            "PaymentMethodType" => "E",
                            "Amount" => $valorTotal
                        ]
                    ];
                } elseif ($isPixPayment) {
                    $payment = $order->getPayment();
                    $payment->setMethod('pix');
                    $payment->save();
                    $order->addStatusHistoryComment('Pagamento em PIX');
                    $paymentMethods = [
                        [
                            "PaymentMethodType" => "D",
                            "Amount" => $valorTotal
                        ]
                    ];
                } else
                {
                    $payment = $order->getPayment();
                    $payment->setMethod('link');
                    $payment->save();
                    $order->addStatusHistoryComment('Pagamento em Link de pagamento');
                    $paymentMethods = [
                        [
                            "PaymentMethodType" => "U",
                            "Amount" => $valorTotal
                        ]
                    ];

                }
            } else {
                $payment = $order->getPayment();
                $payment->setMethod('boleto');
                $payment->save();
                $order->addStatusHistoryComment('Pagamento em Boleto');
                $paymentMethods = [
                    [
                        "PaymentMethodType" => "3",
                        "Amount" => $valorTotal,
                        "BoletoInfo" => [
                            "BillingInfo" => [
                                "Document" => $buyerDocument,
                                "DocumentType" => $documentType,
                                "Address" => [
                                    "Street" => $billingAddress,
                                    "Number" => $numberB,
                                    "Complement" => $complementB,
                                    "Neighborhood" => $complementB != "" ? $complementB : "Centro",
                                    "City" => $billing["city"],
                                    "State" =>  $this->getStateCode($billing["region"]),
                                    "Country" => $billing["country_id"] != null ? $billing["country_id"] : "BR",
                                    "PostalCode" => $billing["postcode"],
                                    "Phone" => $billing["telephone"]
                                ]
                            ]
                        ]
                    ]
                ];
            }
            if (!$isLinkPayment){
            $url  = 'https://' . $this->_tunaEndpointDomain . '/api/Payment/Init';
            $requestbody = [
                'PartnerUniqueID' => $orderId,
                'TokenSession' =>  $tokenSessionParam,
                'Customer' => [
                    'Email' => $billing["email"],
                    'Name' => $fullName,
                    'ID' => $customerID,
                    'Document' => $buyerDocument,
                    'DocumentType' => $documentType
                ],
                "FrontData" => [
                    "SessionID" => $this->_session->getSessionId(),
                    "Origin" => "WEBSITE",
                    "IpAddress" => $order->getRemoteIp(),
                    "CookiesAccepted" => true
                ],
                "PaymentItems" => [
                    "Items" => $itens
                ],
                "PaymentData" => [
                    'Countrycode' => $billing["country_id"] != null ? $billing["country_id"] : "BR",
                    "AntiFraud" => [
                        "DeliveryAddressee" => $fullName
                    ],
                    "DeliveryAddress" => $deliveryAddress,
                    "SalesChannel" => "ECOMMERCE",
                    "Origin" => "TUNA-MAGENTO",
                    "Amount" => $valorTotal,
                    "PaymentMethods" => $paymentMethods
                ]
            ];
            try {
                $bodyJsonRequest = json_encode($requestbody);
                $this->curlClient->addHeader("Content-Type", "application/json");
                $this->curlClient->addHeader("x-tuna-apptoken", $configuredAppKey);
                $this->curlClient->addHeader("x-tuna-account", (string)$this->_scopeConfig->getValue('payment/tuna_payment/credentials/partner_account'));
                $this->curlClient->post($url, $bodyJsonRequest); // Use Curl client to send POST request
                $body = $this->curlClient->getBody(); // Get the response body

                $response = $this->jsonHelper->unserialize($body);
                $response = $this->normalizeResponseKeys($response);
                if (!is_array($response)) {
                    $response = [];
                }

                $responseCode = (string)($response['code'] ?? '');
                $responseStatus = (string)($response['status'] ?? '');
                $responseMethods = isset($response['methods']) && is_array($response['methods']) ? $response['methods'] : [];
                $firstMethod = isset($responseMethods[0]) && is_array($responseMethods[0]) ? $responseMethods[0] : [];

                switch ($responseStatus) {
                    case '0':
                        $order->setStatus('tuna_Started');
                        break;
                    case '1':
                        $order->setStatus('tuna_Authorized');
                        break;
                    case '2':

                        if ( $this->_scopeConfig->getValue('payment/tuna_payment/options/auto_invoice')=="1"){
                            if ($order->canInvoice()) {
                                try{
                                    $invoice = $this->invoiceService->prepareInvoice($order);
                                    $invoice->register();
                                    $invoice->save();

                                    $transactionSave =
                                        $this->transaction
                                            ->addObject($invoice)
                                            ->addObject($invoice->getOrder());
                                    $transactionSave->save();

                                    $this->invoiceSender->send($invoice);
                                    $order->addCommentToStatusHistory(
                                        __('Usuário notificado sobre o pedido #%1.', $invoice->getId())
                                    )->setIsCustomerNotified(true)->save();
                                } catch (\Exception $e) {}
                            }
                        }
                        $order->setStatus('tuna_Captured');
                        break;
                    case '3':
                        $order->setStatus('tuna_Refunded');
                        break;
                    case '4':
                        $order->setStatus('tuna_Denied');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento negado pelo banco emissor.'));
                        break;
                    case '5':
                        $order->setStatus('tuna_Cancelled');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento cancelado.'));
                        break;
                    case '-1':
                        $order->setStatus('tuna_Cancelled');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento cancelado.'));
                        break;
                    case '6':
                        $order->setStatus('tuna_Expired');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento expirado.'));
                        break;
                    case '7':
                        $order->setStatus('tuna_Chargeback');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento negado pelo banco emissor.'));
                        break;
                    case '8':
                        $order->setStatus('tuna_MoneyReceived');
                        break;
                    case '9':
                        $order->setStatus('tuna_PartialCancel');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento cancelado.'));
                        break;
                    case 'A':
                        $order->setStatus('tuna_Error');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Tente novamente.'));
                        break;
                    case 'B':
                        $order->setStatus('tuna_RedFlag');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Tente novamente.'));
                        break;
                    case 'C':
                    case 'P':
                        if ((!$isCryptoPayment)
                            && (!$isPixPayment)
                            && (!$isBoletoPayment))
                            {
                                $order->setStatus('tuna_PendingCapture');
                                    switch ((string)($firstMethod['status'] ?? ''))
                                    {
                                        case '3':
                                        case '4':
                                        case '5':
                                        case '6':
                                        case '7':
                                        case '9':
                                        case '-1':
                                        case 'A':
                                        case 'E':
                                        case 'B':
                                        case 'G':
                                        case 'N':
                                            $this->_checkoutSession->restoreQuote();
                                            throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento negado pelo banco emissor.'));
                                            break;
                                        default:
                                            break;
                                    }
                            }else
                            {
                                $order->setStatus('tuna_PendingCapture');
                            }

                        break;
                    case 'D':
                        $order->setStatus('tuna_PendingAntiFraud');
                        break;
                    case 'E':
                        $order->setStatus('tuna_DeniedAntiFraud');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Pagamento negado pelo banco emissor.'));
                        break;
                }

                if ($responseCode === "1" && ($responseStatus === "C" || $responseStatus === "P")) {
                    $redirectInfo = $firstMethod['redirectinfo'] ?? $firstMethod['redirectInfo'] ?? null;
                    $cryptoInfo = $firstMethod['cryptoinfo'] ?? $firstMethod['cryptoInfo'] ?? null;
                    $pixInfo = $firstMethod['pixinfo'] ?? $firstMethod['pixInfo'] ?? null;

                    if ($isBoletoPayment) {
                        if (is_array($redirectInfo) && !empty($redirectInfo['url'])) {
                            $additionalData = $payment->getAdditionalInformation();
                            $additionalData["boleto_url"] = $redirectInfo['url'];
                            $payment->setData('additional_information', $additionalData);
                            $payment->save();
                            $order->addStatusHistoryComment('Link: '. $redirectInfo['url']);
                        }
                    }
                    if ($isCryptoPayment) {
                        if (is_array($cryptoInfo)) {
                            $additionalData = $payment->getAdditionalInformation();
                            $additionalData["crypto_coin_value"] = $cryptoInfo['coinvalue'] ?? $cryptoInfo['coinValue'] ?? null;
                            $additionalData["crypto_coin_rate_currency"] = $cryptoInfo['coinratecurrency'] ?? $cryptoInfo['coinRateCurrency'] ?? null;
                            $additionalData["crypto_coin_addr"] = $cryptoInfo['coinaddr'] ?? $cryptoInfo['coinAddr'] ?? null;
                            $additionalData["crypto_coin_qrcode_url"] = $cryptoInfo['coinqrcodeurl'] ?? $cryptoInfo['coinQRCodeUrl'] ?? null;
                            $additionalData["crypto_coin"] = $cryptoInfo['coin'] ?? null;
                            $payment->setData('additional_information', $additionalData);
                            $payment->save();
                        }
                    }
                    if ($isPixPayment) {
                        if (is_array($pixInfo)) {
                            $additionalData = $payment->getAdditionalInformation();
                            $pixImageIndo = $pixInfo['qrimage'] ?? $pixInfo['qrImage'] ?? '';
                            if (stripos($pixImageIndo,'data:image') === false
                            && stripos($pixImageIndo,'https://') === false )
                            {
                                $additionalData["pix_image"] = 'data:image/png;base64,'.$pixImageIndo;
                            }else
                            {
                                $additionalData["pix_image"] = $pixImageIndo;
                            }
                            $additionalData["pix_key"] = $pixInfo['qrcontent'] ?? $pixInfo['qrContent'] ?? '';
                            $additionalData["initial_total_value"] = $order->getGrandTotal();
                            $payment->setData('additional_information', $additionalData);
                            $order->addStatusHistoryComment('Chave Pix: '. ($pixInfo['qrcontent'] ?? $pixInfo['qrContent'] ?? ''));
                            $payment->save();
                        }
                    }
                }
            } catch (\Exception $e) {
                throw $e;
            }
            if ($valorTotal != $order->getGrandTotal()) {
                if ($valorTotal - $order->getGrandTotal()>0){
                    $order->addStatusHistoryComment('Acréscimo de R$ ' . number_format($valorTotal - $order->getGrandTotal(), 2, ",", ".") . ' em juros');
                }else{
                    $order->addStatusHistoryComment('Desconto de R$ ' . number_format($valorTotal - $order->getGrandTotal(), 2, ",", ".") . '');
                }
                $order->setGrandTotal($valorTotal);
            }
            $order->addStatusHistoryComment('Valor total da compra: R$ ' . number_format($valorTotal, 2, ",", "."));
            $order->save();
        }else
        {
            $itens=[];
            foreach ($itemsCollection as $item) {
                $imageURL = null;
                #$productimages = $item->getProduct()->getMediaGalleryImages();
                #foreach($productimages as $productimage)
                #{
                #    $imageURL = $productimage['url'];
                #    continue;
                #}
                $valorItem = ($item->getPrice()) ;
                $qtdFloor = ceil($item->getQtyToInvoice());
                $percentualDiscount = ($valorItem*$qtdFloor)/($valorTotal-($order->getDiscountAmount()));
                $valorItem = $this->roundDown(($valorItem +((($order->getDiscountAmount() * $juros)/$qtdFloor)*$percentualDiscount)),2);
                
                $itemData = [
                    "Amount" =>  $valorItem,
                    "ProductDescription" => $item->getProduct()->getName(),
                    "ItemQuantity" => $qtdFloor,
                    "CategoryName" => $item->getProductType(),
                    "ImageURL" => $imageURL,
                    "AntiFraud" => [
                        "Ean" => $item->getSku()
                    ]
                ];
                
                // Adicionar split se configuração estiver ativa
                $splitData = $this->marketplaceSplitResolver->getSplitForItem($item);
                if ($splitData) {
                    $itemData['split'] = $splitData;
                }
                
                $cItem = [$itemData];
                $itens = array_merge($itens, $cItem);
            }
            $baseURL = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
            $url  = 'https://' . $this->_tunaEndpointDomain . '/api/PaymentLink/Create';
            $requestbody = [
                'PartnerUniqueID' => $orderId,
                "Amount" => $valorTotal,
                "description"=>  "Pagamento Pedido Nº:". $orderId,
                "UrlSuccessReturn"=>$baseURL,
                "UrlPendingReturn"=>$baseURL,
                "UrlFailReturn"=>$baseURL,
                "UrlExpiredReturn"=>$baseURL,
                "DeliveryAmount" => $shippingValue,
                "DeliveryDescription" => "Entrega",
                'Customer' => [
                    'Email' => $billing["email"],
                    'Name' => $fullName,
                    'ID' => $customerID,
                    'Document' => $buyerDocument,
                    'DocumentType' => $documentType
                ],
                "expirationinminutes"=>  2880,
                "PaymentMethod"=>  "U",
                "type"=>  "U",
                "MaxInstallments"=>  $this->_scopeConfig->getValue('payment/tuna_payment/credit_card/installments'),
                "BillingAddress" => $deliveryAddress,
                "deliveryaddress" => $deliveryAddress,
                "FrontData" => [
                    "SessionID" => $this->_session->getSessionId(),
                    "Origin" => "WEBSITE",
                    "IpAddress" => $order->getRemoteIp(),
                    "CookiesAccepted" => true
                ],
                "Items" =>   $itens
            ];
            try {
                $bodyJsonRequest = json_encode($requestbody);
                $this->curlClient->addHeader("Content-Type", "application/json");
                $this->curlClient->addHeader("x-tuna-apptoken", $configuredAppKey);
                $this->curlClient->addHeader("x-tuna-account", (string)$this->_scopeConfig->getValue('payment/tuna_payment/credentials/partner_account'));
                $this->curlClient->post($url, $bodyJsonRequest); // Use Curl client to send POST request
                $body = $this->curlClient->getBody(); // Get the response body
                $response = $this->jsonHelper->unserialize($body);
                $response = $this->normalizeResponseKeys($response);
                if (!is_array($response)) {
                    $response = [];
                }

                $responseCode = (string)($response['code'] ?? '');
                $paymentLink = isset($response['paymentlink']) && is_array($response['paymentlink'])
                    ? $response['paymentlink']
                    : (isset($response['paymentLink']) && is_array($response['paymentLink']) ? $response['paymentLink'] : []);

                switch ($responseCode) {
                    case '1':
                            $order->setStatus('tuna_PendingCapture');
                        break;
                    default:
                        $order->setStatus('tuna_Error');
                        $this->_checkoutSession->restoreQuote();
                        throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Tente novamente.'));
                        break;
                }

                if ($responseCode === "1" && !empty($paymentLink['url'])) {
                    $additionalData = $payment->getAdditionalInformation();
                    $additionalData["link_url"] = $paymentLink['url'];
                    $payment->setData('additional_information', $additionalData);
                    $payment->save();
                    $order->addStatusHistoryComment('Link: '. $paymentLink['url']);
                }else
                {
                    $order->setStatus('tuna_Error');
                    $this->_checkoutSession->restoreQuote();
                    throw new \Magento\Framework\Exception\LocalizedException(__('Falha na operação. Tente novamente.'));
                }
            } catch (\Exception $e) {
                throw $e;
            }
            $order->addStatusHistoryComment('Valor total da compra: R$ ' . number_format($valorTotal, 2, ",", "."));
            $order->save();
        }
    }

       #return $this;
    }

    public function getValorFinal($valorTotal, $parcela = -1)
    {
        $tmpJuros = $this->_scopeConfig->getValue('payment/tuna_payment/credit_card/p' . $parcela);
        $juros = 0;
        if ($tmpJuros != '') {
            $juros = (float)$tmpJuros;
        }

        if ($juros == 0) {
            return $valorTotal;
        } else {
            $juros = $juros / 100.00;
            return $valorTotal * (1 + $juros);
        }
    }

    private function normalizeResponseKeys($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : $key;
            $normalized[$normalizedKey] = $this->normalizeResponseKeys($value);
        }

        return $normalized;
    }

    private function extractGuid($value)
    {
        $candidate = trim((string)$value);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $candidate)) {
            return $candidate;
        }

        if (preg_match('/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/', $candidate, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getStateCode($state)
    {
        $code = "BA";
        switch ($state) {
            case 'Bahia':
                $code = "BA";
                break;
            case 'Acre':
                $code = "AC";
                break;
            case 'Alagoas':
                $code = "AL";
                break;
            case 'Amapá':
                $code = "AP";
                break;
            case 'Amazonas':
                $code = "AM";
                break;
            case 'Ceará':
                $code = "CE";
                break;
            case 'Distrito Federal':
                $code = "DF";
                break;
            case 'Espírito Santo':
                $code = "ES";
                break;
            case 'Goiás':
                $code = "GO";
                break;
            case 'Maranhão':
                $code = "MA";
                break;
            case 'Mato Grosso':
                $code = "MT";
                break;
            case 'Mato Grosso do Sul':
                $code = "MS";
                break;
            case 'Minas Gerais':
                $code = "MG";
                break;
            case 'Pará':
                $code = "PA";
                break;
            case 'Paraíba':
                $code = "PB";
                break;
            case 'Paraná':
                $code = "PR";
                break;
            case 'Pernambuco':
                $code = "PE";
                break;
            case 'Piauí':
                $code = "PI";
                break;
            case 'Rio de Janeiro':
                $code = "RJ";
                break;
            case 'Rio Grande do Norte':
                $code = "RN";
                break;
            case 'Rio Grande do Sul':
                $code = "RS";
                break;
            case 'Rondônia':
                $code = "RO";
                break;
            case 'Roraima':
                $code = "RR";
                break;
            case 'Santa Catarina':
                $code = "SC";
                break;
            case 'São Paulo':
                $code = "SP";
                break;
            case 'Sergipe':
                $code = "SE";
                break;
            case 'Tocantins':
                $code = "TO";
                break;
        }
        return $code;
    }
}


