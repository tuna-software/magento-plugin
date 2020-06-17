<?php

namespace Tuna\TunaGateway\Controller\Order;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;

class Update extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $_session;
    protected $curl;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        ScopeConfig $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_pageFactory = $pageFactory;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        return parent::__construct($context);
    }

    public function execute()
    {     
        $appkey = $this->getRequest()->getParam('appkey');
        $orderID = $this->getRequest()->getParam('orderID');
        $status = $this->getRequest()->getParam('status');
        if ($this->scopeConfig->getValue('payment/tuna/appKey')!= $appkey )
        {
            echo print_r("ERROR");
            exit; 
        }
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderID );    
           
        /** change payment status in magento */
        switch ($status.'') {
            case '0':
                $status = ('tuna_Started');
                break;
            case '1':
                $status = ('tuna_Authorized');
                break;
            case '2':
                $status = ('tuna_Captured');
                break;
            case '3':
                $status = ('tuna_Refunded');
                break;
            case '4':
                $status = ('tuna_Denied');
                break;
            case '5':
                $status = ('tuna_Cancelled');
                break;
            case '6':
                $status = ('tuna_Expired');
                break;
            case '7':
                $status = ('tuna_Chargeback');
                break;
            case '8':
                $status = ('tuna_MoneyReceived');
                break;
            case '9':
                $status = ('tuna_PartialCancel');
                break;
            case 'A':
                $status = ('tuna_Error');
                break;
            case 'B':
                $status = ('tuna_RedFlag');
                break;
            case 'C':
                $status = ('tuna_PendingCapture');
                break;
            case 'D':
                $status = ('tuna_PendingAntiFraud');
                break;
            case 'E':
                $status = 'tuna_DeniedAntiFraud';
                break;
          }
        if ($status != $order->getStatus() && $order->getStatus()!= 'complete')
        { 
            $order->addStatusToHistory($status, null, true);
            $order->setStatus($status);
        }
        echo print_r("OK"); 
        exit;
        
    }
}
