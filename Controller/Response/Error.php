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
            return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'checkout/cart'));
        }

        $oldOrderTotal = $order->getBaseGrandTotal();
        $newOrderTotal = $order->getGrandTotal();

        $resultPage->getLayout()->getBlock('tuna.response.error')->setOrderId($order->getIncrementId().'');
        $resultPage->getLayout()->getBlock('tuna.response.error')->setStatus($this->status());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setOrderProducts($this->products());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setDiscountExtra($order->getDiscountAmount());
        $resultPage->getLayout()->getBlock('tuna.response.error')->setShippingAmount($order->getBaseShippingAmount());
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
        $order = $this->_objectManager->create('Magento\Sales\Model\Order');
        $incrementId = (string)$this->getSessionValue('order_id', '');

        if ($incrementId !== '') {
            $order = $order->loadByIncrementId($incrementId);
            if ($order->getId()) {
                return $order;
            }
        }

        $lastRealOrder = $this->_objectManager->create('Magento\Checkout\Model\Session')->getLastRealOrder();
        if ($lastRealOrder && $lastRealOrder->getId()) {
            return $lastRealOrder;
        }

        return $order;
    }

    private function status()
    {
        return $this->getSessionValue('order_status', '');
    }
    private function products()
    {
        return $this->getSessionValue('order_products', []);
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
