<?php

namespace Tuna\TunaGateway\Controller\Payment;

use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Checkout\Model\Session as CheckoutSession;

class Request extends \Magento\Framework\App\Action\Action
{
    private $_checkoutSession;
    protected $resultJsonFactory;
    protected $result;
    private $orderId;
    protected $scopeConfig;
    private  $_isScopePrivate;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        CheckoutSession $checkoutSession,
        ScopeConfig $scopeConfig
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $this->_objectManager->create('\Magento\Framework\Controller\Result\JsonFactory');
        $this->result = $this->resultJsonFactory->create();
        $this->_isScopePrivate = true;
        $this->_checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $lastRealOrder = $this->_checkoutSession->getLastRealOrder();
        $order = $lastRealOrder;
        if ($lastRealOrder && $lastRealOrder->getIncrementId()) {
            $freshOrder = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($lastRealOrder->getIncrementId());
            if ($freshOrder && $freshOrder->getId()) {
                $order = $freshOrder;
            }
        }

        if (is_null($order->getPayment())) {
            throw new \Magento\Framework\Exception\NotFoundException(__('No order associated.'));
        }
        $payment = $order->getPayment();
        $paymentData = $payment->getData();

        if ($paymentData['method'] === 'tuna' ||
        $paymentData['method'] === 'crypto' ||
        $paymentData['method'] === 'pix' ||
        $paymentData['method'] === 'boleto' ||
        $paymentData['method'] === 'credit' ||
        $paymentData['method'] === 'link' ||
        $paymentData['method'] === 'googlepay' ||
        $paymentData['method'] === 'applepay') {
            $this->orderId = $order->getIncrementId();
            $orderStatusText = '';
            $orderStatus = $order->getStatus();
            $itemsCollection = $order->getAllVisibleItems();
            $orderProducts = [];
            foreach ($itemsCollection as $item) {
                $cItem = [[
                    "Amount" => $item->getPrice(),
                    "ProductUrl" =>  $item->getProduct()->getProductUrl(),
                    "ProductDescription" => $item->getProduct()->getName(),
                    "ItemQuantity" => $item->getQtyToInvoice()
                ]];
                $orderProducts  = array_merge($orderProducts, $cItem);
            }

            if (
                $orderStatus == "tuna_Started" ||
                $orderStatus == "tuna_Authorized" ||
                $orderStatus == "tuna_Captured" ||
                $orderStatus == "tuna_PendingCapture"
            ) {

                $additionalInformation = $payment->getAdditionalInformation() ?: [];
                $paymentMethod = (string)($paymentData['method'] ?? '');

                $isBoletoPayment = $additionalInformation["is_boleto_payment"] ?? ($paymentMethod === 'boleto' ? "true" : "false");

                if ($isBoletoPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_boleto') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }

                $isCryptoPayment = $additionalInformation["is_crypto_payment"] ?? ($paymentMethod === 'crypto' ? "true" : "false");

                if ($isCryptoPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_crypto') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }

                $isPixPayment = $additionalInformation["is_pix_payment"] ?? ($paymentMethod === 'pix' ? "true" : "false");

                if ($isPixPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_pix') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }
                $isLinkPayment = $additionalInformation["is_link_payment"] ?? ($paymentMethod === 'link' ? "true" : "false");

                if ($isLinkPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_link') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }
                $this->_checkoutSession->setData(
                    'tuna_payment' , [
                        'payment_type'  => $paymentData['method'],
                        'order_id'      => $this->orderId,
                        'order_products' => $orderProducts,
                        'order_status' => $orderStatus,
                        'is_boleto' => $isBoletoPayment,
                        'is_link' => $isLinkPayment,
                        'is_crypto' => $isCryptoPayment,
                        'is_pix' => $isPixPayment,
                        'boleto_url' => $isBoletoPayment == "true" ? ($additionalInformation["boleto_url"] ?? "") : "",
                        'crypto_coin_value' => $isCryptoPayment == "true" ? ($additionalInformation["crypto_coin_value"] ?? "") : "",
                        'crypto_coin_rate_currency' => $isCryptoPayment == "true" ? ($additionalInformation["crypto_coin_rate_currency"] ?? "") : "",
                        'crypto_coin_addr' => $isCryptoPayment == "true" ? ($additionalInformation["crypto_coin_addr"] ?? "") : "",
                        'crypto_coin_qrcode_url' => $isCryptoPayment == "true" ? ($additionalInformation["crypto_coin_qrcode_url"] ?? "") : "",
                        'crypto_coin' => $isCryptoPayment == "true" ? ($additionalInformation["crypto_coin"] ?? "") : "",
                        'pix_image' => $isPixPayment == "true" ? ($additionalInformation["pix_image"] ?? "") : "",
                        'pix_key' => $isPixPayment == "true" ? ($additionalInformation["pix_key"] ?? "") : "",
                        'link_url' => $isLinkPayment == "true" ? ($additionalInformation["link_url"] ?? "") : "",
                    ]
                );
                return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/success'));
            } else {
                switch ($orderStatus) {
                    case 'tuna_Denied':
                          $orderStatusText = 'Pagamento negado pelo banco emissor.';
                        break;
                    case 'tuna_Cancelled':
                          $orderStatusText = 'Pagamento cancelado.';
                        break;
                    case 'tuna_Expired':
                          $orderStatusText = 'Pagamento expirado.';
                        break;
                    case 'tuna_Error':
                    case 'tuna_RedFlag':
                           $orderStatusText = 'Erro no pagamento.';
                        break;
                    case 'tuna_PendingAntiFraud':
                          $orderStatusText = 'Pagamento em análise. Aguarde.';
                        break;
                    case 'tuna_DeniedAntiFraud':
                          $orderStatusText = 'Pagamento negado.';
                        break;
                  }
                $this->_checkoutSession->setData(
                    'tuna_payment', [
                        'payment_type'  => $paymentData['method'],
                        'order_id'      => $this->orderId,
                        'order_products' => $orderProducts,
                        'order_status' => $orderStatusText
                    ]
                );
                return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
            }
        }
    }

    private function baseUrl()
    {
        return $this->_objectManager->create('Magento\Store\Model\StoreManagerInterface')->getStore()->getBaseUrl();
    }

    private function session()
    {
        return $this->_objectManager->create('Magento\Framework\Session\SessionManager');
    }
}
