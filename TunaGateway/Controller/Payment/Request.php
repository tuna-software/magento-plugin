<?php

namespace Tuna\TunaGateway\Controller\Payment;

class Request extends \Magento\Framework\App\Action\Action
{
    private $_checkoutSession;
    protected $resultJsonFactory;
    protected $result;
    private $_payment;

    public function __construct(\Magento\Framework\App\Action\Context $context) {
        parent::__construct($context);
        $this->resultJsonFactory = $this->_objectManager->create('\Magento\Framework\Controller\Result\JsonFactory');
        $this->result = $this->resultJsonFactory->create();

        $this->_checkoutSession = $this->_objectManager->create('\Magento\Checkout\Model\Session');

        // $this->_payment = new PaymentMethod(
        //     $this->_objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface'),
        //     $this->_checkoutSession,
        //     $this->_objectManager->create('\Magento\Directory\Api\CountryInformationAcquirerInterface'),
        //     $this->_objectManager->create('Magento\Framework\Module\ModuleList')
        // );
    }

    public function execute()
    {
        echo '<pre>';print_r("Compra completada! #TunaRULES"); 
        // $lastRealOrder = $this->_checkoutSession->getLastRealOrder();
    }

}