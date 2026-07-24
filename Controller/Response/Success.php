<?php

namespace Tuna\TunaGateway\Controller\Response;

class Success extends \Magento\Framework\App\Action\Action
{

    /** @var  \Magento\Framework\View\Result\Page */
    protected $_resultPageFactory;
    protected $_scopeConfig;
    protected $resultRedirect;

    /**
     * Checkout constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\ResultFactory $result
        ) {
        parent::__construct($context);

        /** @var  _resultPageFactory */
        $this->_resultPageFactory = $resultPageFactory;
        $this->_scopeConfig = $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
        $this->resultRedirect = $result;
    }

    /**
     * Show payment page
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute()
    {
        /** @var \Magento\Framework\View\Result\PageFactory $resultPage */
        $resultPage = $this->_resultPageFactory->create();

        $order = $this->order();
        if (!$order->getId()) {
            return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
        }

        $payment = $order->getPayment();
        $additionalInformation = $payment ? ($payment->getAdditionalInformation() ?: []) : [];
        $paymentMethod = strtolower((string)($payment ? $payment->getMethod() : ''));

        $oldOrderTotal = $order->getBaseGrandTotal();
        $newOrderTotal = $order->getGrandTotal();

        $blockInstance = $resultPage->getLayout()->getBlock('tuna.response.success');

        $resultPage->getLayout()->getBlock('tuna.response.success')->setOrderId($order->getIncrementId().'');
        $resultPage->getLayout()->getBlock('tuna.response.success')->setDiscountExtra($order->getDiscountAmount());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setShippingAmount($order->getBaseShippingAmount());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setOldOrderTotal($oldOrderTotal);
        $resultPage->getLayout()->getBlock('tuna.response.success')->setNewOrderTotal($newOrderTotal);
        $resultPage->getLayout()->getBlock('tuna.response.success')->setOrderProducts($this->products($order));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setStatus($order->getStatus());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setBoletoURL((string)($additionalInformation['boleto_url'] ?? $this->boletoURL()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsBoleto((string)($additionalInformation['is_boleto_payment'] ?? ($paymentMethod === 'boleto' ? 'true' : $this->isBoleto())));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsCrypto((string)($additionalInformation['is_crypto_payment'] ?? ($paymentMethod === 'crypto' ? 'true' : $this->isCrypto())));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsLink((string)($additionalInformation['is_link_payment'] ?? ($paymentMethod === 'link' ? 'true' : $this->isLink())));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinValue((string)($additionalInformation['crypto_coin_value'] ?? $this->cryptoCoinValue()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinRateCurrency((string)($additionalInformation['crypto_coin_rate_currency'] ?? $this->cryptoCoinRateCurrency()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinAddr((string)($additionalInformation['crypto_coin_addr'] ?? $this->cryptoCoinAddr()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinQRCodeURL((string)($additionalInformation['crypto_coin_qrcode_url'] ?? $this->cryptoCoinQRCodeURL()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoin((string)($additionalInformation['crypto_coin'] ?? $this->cryptoCoin()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsPix((string)($additionalInformation['is_pix_payment'] ?? ($paymentMethod === 'pix' ? 'true' : $this->isPix())));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setPixImage((string)($additionalInformation['pix_image'] ?? $this->pixImage()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setPixKey((string)($additionalInformation['pix_key'] ?? $this->pixKey()));
        $resultPage->getLayout()->getBlock('tuna.response.success')->setLinkURL((string)($additionalInformation['link_url'] ?? $this->linkURL()));

        //$this->clearSession();
        return $resultPage;
    }

    private function clearSession()
    {  
        $this->_objectManager->create('Magento\Framework\Session\SessionManager')->getData(
        'tuna_payment', true
        );
        
        $this->_objectManager->create('Magento\Framework\Session\SessionManager')->unsetData('tuna_payment');
    }

    /**
     * Get order
     *
     * @return \Magento\Sales\Model\Order
     */
    private function order()
    {
        $order = $this->_objectManager->create('Magento\Sales\Model\Order');
        $lastRealOrder = $this->_objectManager->create('Magento\Checkout\Model\Session')->getLastRealOrder();
        if ($lastRealOrder && $lastRealOrder->getId()) {
            return $lastRealOrder;
        }

        $incrementId = (string)$this->getSessionValue('order_id', '');

        if ($incrementId !== '') {
            $order = $order->loadByIncrementId($incrementId);
            if ($order->getId()) {
                return $order;
            }
        }

        return $order;
    }

    private function products($order = null)
    {
        $sessionProducts = $this->getSessionValue('order_products', []);
        if (is_array($sessionProducts) && count($sessionProducts) > 0) {
            return $sessionProducts;
        }

        if ($order && $order->getId()) {
            $orderProducts = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $orderProducts[] = [
                    'Amount' => (float)$item->getPrice(),
                    'ProductUrl' => (string)$item->getProduct()->getProductUrl(),
                    'ProductDescription' => (string)$item->getName(),
                    'ItemQuantity' => (float)$item->getQtyOrdered(),
                ];
            }

            return $orderProducts;
        }

        return [];
    }

    private function status()
    {
        return $this->getSessionValue('order_status', '');
    }
    private function boletoURL()
    {
        return $this->getSessionValue('boleto_url', '');
    }
    private function linkURL()
    {
        return $this->getSessionValue('link_url', '');
    }
    private function cryptoCoinValue()
    {
        return $this->getSessionValue('crypto_coin_value', '');
    }
    private function cryptoCoinRateCurrency()
    {
        return $this->getSessionValue('crypto_coin_rate_currency', '');
    }
    private function cryptoCoinAddr()
    {
        return $this->getSessionValue('crypto_coin_addr', '');
    }
    private function cryptoCoinQRCodeURL()
    {
        return $this->getSessionValue('crypto_coin_qrcode_url', '');
    }
    private function cryptoCoin()
    {
        return $this->getSessionValue('crypto_coin', '');
    }
    private function pixKey()
    {
        return $this->getSessionValue('pix_key', '');
    }
    private function pixImage()
    {
        return $this->getSessionValue('pix_image', '');
    }
    private function isBoleto()
    {
        return $this->getSessionValue('is_boleto', 'false');
    }
    private function isLink()
    {
        return $this->getSessionValue('is_link', 'false');
    }
    private function isCrypto()
    {
        return $this->getSessionValue('is_crypto', 'false');
    }
    private function isPix()
    {
        return $this->getSessionValue('is_pix', 'false');
    }

    private function baseUrl()
    {
        return $this->_objectManager->create('Magento\Store\Model\StoreManagerInterface')->getStore()->getBaseUrl();
    }

    private function getSessionValue($key, $default = null)
    {
        $data = $this->session();
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /**
     * Get session
     *
     * @return array
     */
    private function session()
    {
        $data = $this->_objectManager->create('Magento\Framework\Session\SessionManager')->getData('tuna_payment');

        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {
            return (array)$data;
        }

        return [];
    }
}