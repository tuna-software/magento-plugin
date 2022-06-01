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
        $appkey = $this->getRequest()->getHeader('Authorization');
        $body = $this->jsonHelper->jsonDecode(file_get_contents('php://input'));
        $orderID = $body['partnerUniqueId'];
        $status = $body['statusId'];   
        if ($orderID==null)
        {
            echo print_r("ERROR - partneruniqueid parameter not found.");
            echo print_r(file_get_contents('php://input'));
            exit;
        }
        if ($status==null)
        {
            echo print_r("ERROR - status parameter not found.");
            echo print_r(file_get_contents('php://input'));
            exit;
        }
        if ($appkey==null)
        {
            echo print_r("ERROR - authorization header not found.");
            echo print_r($this->getRequest()->getHeader('Authorization'));
            exit;
        }
        if ('Bearer '.$this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey')!= $appkey )
        {
            echo print_r("ERROR - invalid header.");
            exit; 
        }
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderID );
        if ($order==null)
        {
            echo print_r("ERROR - order not found.");
            exit;
        }
        $payment = $order->getPayment();    
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
            case 'P':
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
            $order->save();
        }
        echo print_r("OK"); 
        exit;
        
    }
    public function saveLog2($txt)
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
}
