<?php

namespace Tuna\TunaGateway\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;

class CreateTunaOrder implements ObserverInterface
{
    protected $_scopeConfig;
    protected $_tunaEndpointDomain;
    protected $_code = "tuna";
    protected $_coreSession;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        CoreSession $coreSession
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
        $this->_coreSession = $coreSession;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        //verify transaction
        if ($order->getStatus() == 'tuna_Started') {
            $orderId = $order->getId();
            $shipping = $order->getShippingAddress();
            $billing = $order->getBillingAddress();
            $payment = $order->getPayment();

            $tokenSessionParam = $payment->getAdditionalInformation()["session_id"];

            $fullName =  $billing["firstname"] . " " . $billing["lastname"];

            $this->_coreSession->start();
            $customerID = $this->_coreSession->getCostumerID();

            $isNewCustomer = !$order->getCustomerId();
            if (!$isNewCustomer) {
                $customerID = $order->getCustomerId();
            }
            $itens = [];

            $creditCardData = json_decode($payment->getAdditionalInformation()["credit_card_data"]);

            $valorTotal = $this->getValorFinal($order->getGrandTotal(), 1);
            $juros = 1;

            if ($creditCardData != null && count($creditCardData) > 0) {
                $creditCardAmount = $creditCardData[0]->credit_card_amount + ($creditCardData[1]->credit_card_amount ?? 0);
                if ($creditCardAmount != $valorTotal) {
                    $order->setStatus('tuna_Cancelled');
                    $order->addStatusHistoryComment('Os valores informados para cada cartão não totalizam o valor da compra.');
                    $order->setGrandTotal($valorTotal);
                    $order->save();
                    return;
                }
                $valorTotalComJuros = $this->getValorFinal($creditCardData[0]->credit_card_amount, $creditCardData[0]->credit_card_installments) +
                    $this->getValorFinal($creditCardData[1]->credit_card_amount ?? 0, $creditCardData[1]->credit_card_installments ?? 1);

                $juros = $valorTotalComJuros / $valorTotal;
                $valorTotal = round($valorTotalComJuros, 2);
            }

            $itemsCollection = $order->getAllVisibleItems();
            $tmpvalorTotalPendente = $valorTotal - $order->getBaseShippingAmount();
            foreach ($itemsCollection as $item) {
                $valorItem = round($item->getPrice() * $juros, 2);
                $tmpvalorTotalPendente = $tmpvalorTotalPendente - ($valorItem * $item->getQtyToInvoice());
                $cItem = [[
                    "Amount" =>  $valorItem,
                    "ProductDescription" => $item->getProduct()->getName(),
                    "ItemQuantity" => $item->getQtyToInvoice(),
                    "CategoryName" => $item->getProductType(),
                    "AntiFraud" => [
                        "Ean" => $item->getSku()
                    ]
                ]];
                $itens = array_merge($itens, $cItem);
            }
            $deliveryAddress = [];
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
            if (strlen($payment->getAdditionalInformation()["buyer_document"]) > 17) {
                $documentType = "CNPJ";
            }
            $paymentMethods = [];
            if ($payment->getAdditionalInformation()["is_boleto_payment"] == "false") {
                if (
                    $payment->getAdditionalInformation()["is_pix_payment"] == "false"
                    && $payment->getAdditionalInformation()["is_crypto_payment"] == "false"
                ) {
                    $order->addStatusHistoryComment('Pagamento em Cartão de crédito');
                    $payment = $order->getPayment();
                    $payment->setMethod('credit');
                    $payment->save();

                    foreach ($creditCardData as $creditCard) {
                        $paymentMethod = [
                            "PaymentMethodType" => "1",
                            "amount" => round($creditCard->credit_card_amount * $juros, 2),
                            "Installments" => $creditCard->credit_card_installments,
                            "CardInfo" => [
                                "TokenProvider" => "Tuna",
                                "CardNumber" => "",
                                "CardHolderName" => $creditCard->card_holder_name,
                                "BrandName" => $creditCard->credit_card_brand,
                                "ExpirationMonth" =>  $creditCard->credit_card_expiration_month * 1,
                                "ExpirationYear" =>  $creditCard->credit_card_expiration_year * 1,
                                "Token" => $creditCard->credit_card_token,
                                "TokenSingleUse" => $isNewCustomer ? 1 : 0,
                                "SaveCard" => false,
                                "BillingInfo" => [
                                    "Document" => $payment->getAdditionalInformation()["buyer_document"],
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
                        array_push($paymentMethods, $paymentMethod);
                    }
                } elseif ($payment->getAdditionalInformation()["is_crypto_payment"] == "true") {
                    $payment = $order->getPayment();
                    $payment->setMethod('crypto');
                    $payment->save();
                    $order->addStatusHistoryComment('Pagamento em Bitcoin');
                    $paymentMethods = [
                        [
                            "PaymentMethodType" => "E"
                        ]
                    ];
                } else {
                    $payment = $order->getPayment();
                    $payment->setMethod('pix');
                    $payment->save();
                    $order->addStatusHistoryComment('Pagamento em PIX');
                    $paymentMethods = [
                        [
                            "PaymentMethodType" => "D"
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
                        "BoletoInfo" => [
                            "BillingInfo" => [
                                "Document" => $payment->getAdditionalInformation()["buyer_document"],
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
            $url  = 'https://' . $this->_tunaEndpointDomain . '/api/Payment/Init';
            $requestbody = [
                'AppToken' => $this->_scopeConfig->getValue('payment/tuna_payment/credentials/appKey'),
                'Account' => $this->_scopeConfig->getValue('payment/tuna_payment/credentials/partner_account'),
                'PartnerUniqueID' => $orderId,
                'TokenSession' =>  $tokenSessionParam,
                'Customer' => [
                    'Email' => $billing["email"],
                    'Name' => $fullName,
                    'ID' => $customerID,
                    'Document' => $payment->getAdditionalInformation()["buyer_document"],
                    'DocumentType' => $documentType
                ],
                "FrontData" => [
                    "SessionID" => $this->_session->getSessionId(),
                    "Origin" => "WEBSITE",
                    "IpAddress" => $order->getRemoteIp(),
                    "CookiesAccepted" => true
                ],
                "ShippingItems" => [
                    "Items" => [[
                        "Type" => $order->getShippingDescription(),
                        "Amount" => $order->getBaseShippingAmount() + $tmpvalorTotalPendente,
                        "Code" =>  ""
                    ]]
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
                    "Amount" => $valorTotal,
                    "PaymentMethods" => $paymentMethods
                ]
            ];

            /* Create curl factory */
            $httpAdapter = $this->curlFactory->create();
            $bodyJsonRequest = json_encode($requestbody);
            //  $this->saveLog($bodyJsonRequest);
            $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', ["Content-Type:application/json"], $bodyJsonRequest);

            $result = $httpAdapter->read();

            $body = \Zend_Http_Response::extractBody($result);
            // $this->saveLog($body);
            try {
                $response = $this->jsonHelper->jsonDecode($body);
                switch (strval($response["status"])) {
                    case '0':
                        $order->setStatus('tuna_Started');
                        break;
                    case '1':
                        $order->setStatus('tuna_Authorized');
                        break;
                    case '2':
                        $order->setStatus('tuna_Captured');
                        break;
                    case '3':
                        $order->setStatus('tuna_Refunded');
                        break;
                    case '4':
                        $order->setStatus('tuna_Denied');
                        break;
                    case '5':
                        $order->setStatus('tuna_Cancelled');
                        break;
                    case '-1':
                        $order->setStatus('tuna_Cancelled');
                        break;
                    case '6':
                        $order->setStatus('tuna_Expired');
                        break;
                    case '7':
                        $order->setStatus('tuna_Chargeback');
                        break;
                    case '8':
                        $order->setStatus('tuna_MoneyReceived');
                        break;
                    case '9':
                        $order->setStatus('tuna_PartialCancel');
                        break;
                    case 'A':
                        $order->setStatus('tuna_Error');
                        break;
                    case 'B':
                        $order->setStatus('tuna_RedFlag');
                        break;
                    case 'C':
                    case 'P':
                        $order->setStatus('tuna_PendingCapture');
                        break;
                    case 'D':
                        $order->setStatus('tuna_PendingAntiFraud');
                        break;
                    case 'E':
                        $order->setStatus('tuna_DeniedAntiFraud');
                        break;
                }
                if (strval($response["code"]) == "1" && ($response["status"] == "C" || $response["status"] == "P")) {
                    if ($payment->getAdditionalInformation()["is_boleto_payment"] == "true") {
                        if ($response["methods"] != null && $response["methods"][0]["redirectInfo"] != null) {
                            $additionalData = $payment->getAdditionalInformation();
                            $additionalData["boleto_url"] = $response["methods"][0]["redirectInfo"]["url"];
                            $payment->setData('additional_information', $additionalData);
                            $payment->save();
                        }
                    }
                    if ($payment->getAdditionalInformation()["is_crypto_payment"] == "true") {
                        if ($response["methods"] != null && $response["methods"][0]["cryptoInfo"] != null) {
                            $additionalData = $payment->getAdditionalInformation();
                            $additionalData["crypto_coin_value"] = $response["methods"][0]["cryptoInfo"]["coinValue"];
                            $additionalData["crypto_coin_rate_currency"] = $response["methods"][0]["cryptoInfo"]["coinRateCurrency"];
                            $additionalData["crypto_coin_addr"] = $response["methods"][0]["cryptoInfo"]["coinAddr"];
                            $additionalData["crypto_coin_qrcode_url"] = $response["methods"][0]["cryptoInfo"]["coinQRCodeUrl"];
                            $additionalData["crypto_coin"] = $response["methods"][0]["cryptoInfo"]["coin"];
                            $payment->setData('additional_information', $additionalData);
                            $payment->save();
                        }
                    }
                    if ($payment->getAdditionalInformation()["is_pix_payment"] == "true") {
                        if ($response["methods"] != null && $response["methods"][0]["pixInfo"] != null) {
                            $additionalData = $payment->getAdditionalInformation();
                            $additionalData["pix_image"] = $response["methods"][0]["pixInfo"]["qrImage"];
                            $additionalData["pix_key"] = $response["methods"][0]["pixInfo"]["qrContent"];
                            $payment->setData('additional_information', $additionalData);
                            $payment->save();
                        }
                    }
                }
            } catch (\Exception $e) {
                $order->setStatus('tuna_Cancelled');
            }
            if ($valorTotal != $order->getGrandTotal()) {
                $order->addStatusHistoryComment('Acréscimo de R$ ' . number_format($valorTotal - $order->getGrandTotal(), 2, ",", ".") . ' em juros');
                $order->setGrandTotal($valorTotal);
            }
            $order->save();
        }

        #return $this;
    }

    public function getValorFinal($valorTotal, $parcela)
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

    public function saveLog($txt)
    {
        $filename = "/var/www/html/app/code/Tuna/TunaGateway/newfile.txt";
        $file = fopen($filename, "a");

        if ($file == false) {
            echo ("Error in opening new file");
            exit();
        }
        fwrite($file, $txt . " | " . date("j/n/Y h:i:s") . "\n");
        fclose($file);
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
