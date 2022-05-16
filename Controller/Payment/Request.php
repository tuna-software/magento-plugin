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

        if (is_null($lastRealOrder->getPayment())) {
            throw new \Magento\Framework\Exception\NotFoundException(__('No order associated.'));
        }
        $payment = $lastRealOrder->getPayment();
        $paymentData = $payment->getData();

        if ($paymentData['method'] === 'tuna' ||
        $paymentData['method'] === 'crypto' ||
        $paymentData['method'] === 'pix' ||
        $paymentData['method'] === 'boleto' ||
        $paymentData['method'] === 'credit') {
            $this->orderId = $lastRealOrder->getId();
            $orderStatusText = '';
            $orderStatus = $lastRealOrder->getStatus();            
            $itemsCollection = $lastRealOrder->getAllVisibleItems();
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
               
                $isBoletoPayment = $payment->getAdditionalInformation()["is_boleto_payment"];

                if ($isBoletoPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_boleto') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }

                $isCryptoPayment = $payment->getAdditionalInformation()["is_crypto_payment"];

                if ($isCryptoPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_crypto') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }

                $isPixPayment = $payment->getAdditionalInformation()["is_pix_payment"];

                if ($isPixPayment == "true" && $this->scopeConfig->getValue('payment/tuna_payment/options/allow_pix') === "0") {
                    return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
                }
                $this->session()->setData(
                    'tuna_payment' , [
                        'payment_type'  => $paymentData['method'],
                        'order_id'      => $this->orderId,
                        'order_products' => $orderProducts,
                        'order_status' => $orderStatus,
                        'is_boleto' => $isBoletoPayment,
                        'is_crypto' => $isCryptoPayment,
                        'is_pix' => $isPixPayment,
                        'boleto_url' => $isBoletoPayment == "true" ? $payment->getAdditionalInformation()["boleto_url"] : "",
                        'crypto_coin_value' => $isCryptoPayment == "true" ? $payment->getAdditionalInformation()["crypto_coin_value"] : "",
                        'crypto_coin_rate_currency' => $isCryptoPayment == "true" ? $payment->getAdditionalInformation()["crypto_coin_rate_currency"] : "",
                        'crypto_coin_addr' => $isCryptoPayment == "true" ? $payment->getAdditionalInformation()["crypto_coin_addr"] : "",
                        'crypto_coin_qrcode_url' => $isCryptoPayment == "true" ? $payment->getAdditionalInformation()["crypto_coin_qrcode_url"] : "",
                        'crypto_coin' => $isCryptoPayment == "true" ? $payment->getAdditionalInformation()["crypto_coin"] : "",
                        'pix_image' => $isPixPayment == "true" ? $payment->getAdditionalInformation()["pix_image"] : "",
                        'pix_key' => $isPixPayment == "true" ? $payment->getAdditionalInformation()["pix_key"] : "",
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
                $this->session()->setData(
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
