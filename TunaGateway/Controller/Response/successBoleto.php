<?php

namespace Tuna\TunaGateway\Controller\Response;

class SuccessBoleto extends \Magento\Framework\App\Action\Action
{

    /** @var  \Magento\Framework\View\Result\Page */
    protected $_resultPageFactory;
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
        $blockInstance = $resultPage->getLayout()->getBlock('tuna.response.success.boleto');
        $resultPage->getLayout()->getBlock('tuna.response.success.boleto')->setOrderId($this->order()->getIncrementId());
        $resultPage->getLayout()->getBlock('tuna.response.success.boleto')->setOrderProducts($this->products());
        $resultPage->getLayout()->getBlock('tuna.response.success.boleto')->setBoletoURL($this->boletoURL());
        $resultPage->getLayout()->getBlock('tuna.response.success.boleto')->setStatus($this->status());

        $this->clearSession();
        return $resultPage;
    }

    private function clearSession()
    {
        $this->_objectManager->create('Magento\Framework\Session\SessionManager')->clearStorage();
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

    private function boletoURL()
    {
        return $this->session()->boleto_url;
    }

    private function status()
    {
        return $this->session()->order_status;
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