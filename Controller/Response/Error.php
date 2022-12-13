<?php

namespace Tuna\TunaGateway\Controller\Response;

class Error extends \Magento\Framework\App\Action\Action
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

        $resultPage->getLayout()->getBlock('tuna.response.error')->setOrderId($this->order()->getIncrementId().'');
        $resultPage->getLayout()->getBlock('tuna.response.error')->setStatus($this->status());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setOrderProducts($this->products());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setDiscountExtra($this->order()->getDiscountAmount());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setShippingAmount($this->order()->getBaseShippingAmount());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setOldOrderTotal($oldOrderTotal);
        $resultPage->getLayout()->getBlock('tuna.response.error')->setNewOrderTotal($newOrderTotal);
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
        return $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($this->session()->order_id);
    }

    private function status()
    {
        return $this->session()->order_status;
    }
    private function products()
    {
        return $this->session()->order_products;
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