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

        $oldOrderTotal = $this->order()->getBaseGrandTotal();
        $newOrderTotal = $this->order()->getGrandTotal();

        $blockInstance = $resultPage->getLayout()->getBlock('tuna.response.success');

        $resultPage->getLayout()->getBlock('tuna.response.success')->setOrderId($this->order()->getId());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setDiscountExtra($this->order()->getDiscountAmount());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setShippingAmount($this->order()->getBaseShippingAmount());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setOldOrderTotal($oldOrderTotal);
        $resultPage->getLayout()->getBlock('tuna.response.success')->setNewOrderTotal($newOrderTotal);
        $resultPage->getLayout()->getBlock('tuna.response.success')->setOrderProducts($this->products());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setStatus($this->order()->getStatus());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setBoletoURL($this->boletoURL());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsBoleto($this->isBoleto());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsCrypto($this->isCrypto());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinValue($this->cryptoCoinValue());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinRateCurrency($this->cryptoCoinRateCurrency());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinAddr($this->cryptoCoinAddr());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoinQRCodeURL($this->cryptoCoinQRCodeURL());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setCryptoCoin($this->cryptoCoin());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setIsPix($this->isPix());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setPixImage($this->pixImage());
        $resultPage->getLayout()->getBlock('tuna.response.success')->setPixKey($this->pixKey());

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
        return $this->_objectManager->create('Magento\Sales\Model\Order')->load($this->session()->order_id);
    }

    private function products()
    {
        return $this->session()->order_products;
    }

    private function status()
    {
        return $this->session()->order_status;
    }
    private function boletoURL()
    {
        return $this->session()->boleto_url;
    }
    private function cryptoCoinValue()
    {
        return $this->session()->crypto_coin_value;
    }
    private function cryptoCoinRateCurrency()
    {
        return $this->session()->crypto_coin_rate_currency;
    }
    private function cryptoCoinAddr()
    {
        return $this->session()->crypto_coin_addr;
    }
    private function cryptoCoinQRCodeURL()
    {
        return $this->session()->crypto_coin_qrcode_url;
    }
    private function cryptoCoin()
    {
        return $this->session()->crypto_coin;
    }
    private function pixKey()
    {
        return $this->session()->pix_key;
    }
    private function pixImage()
    {
        return $this->session()->pix_image;
    }
    private function isBoleto()
    {
        return $this->session()->is_boleto;
    }
    private function isCrypto()
    {
        return $this->session()->is_crypto;
    }
    private function isPix()
    {
        return $this->session()->is_pix;
    }
    /**
     * Get session
     *
     * @return object
     */
    private function session()
    {
        return (object)$this->_objectManager->create('Magento\Framework\Session\SessionManager')->getData(
            'tuna_payment'
        );
    }
}