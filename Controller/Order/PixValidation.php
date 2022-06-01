<?php

namespace Tuna\TunaGateway\Controller\Order;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;

class PixValidation extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $_session;
    protected $curl;
    protected $_tunaEndpointDomain;

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
        if ($this->scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') == 'production'){
            $this->_tunaEndpointDomain = 'https://engine.tunagateway.com/api/Payment/Status';
          }else{
            $this->_tunaEndpointDomain = 'https://sandbox.tuna-demo.uy/api/Payment/Status';
        }
        return parent::__construct($context);
    }

    public function execute()
    {       
        $orderID = $this->getRequest()->getParam('partnerUniqueId');
          
        if ($orderID==null)
        {
            echo print_r("ERROR - partneruniqueid parameter not found.");
            exit;
        }
        
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderID );
        if ($order==null)
        {
            echo print_r("ERROR - order not found.");
            exit;
        }
        $url = $this->_tunaEndpointDomain;
        $cItem = [
            "AppToken" => $this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey'),
            "Account" => $this->scopeConfig->getValue('payment/tuna_payment/credentials/partner_account'),
			"PartnerUniqueID" => $order->getId() . "",
			"PaymentDate" => date('Y-m-d', strtotime($order->getCreatedAt()) + 19800)
		];

        $bodyJsonRequest = json_encode($cItem);
         
        /* Create curl factory */
        $httpAdapter = $this->curlFactory->create();
        /* Forth parameter is POST body */
        $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', ["Content-Type:application/json"], $bodyJsonRequest);
        $result = $httpAdapter->read();
        $body = \Zend_Http_Response::extractBody($result);
         
        /* convert JSON to Array */
        $response = $this->jsonHelper->jsonDecode($body);
        $status  = $response["status"];


   
        $payment = $order->getPayment();  
        if ($response["code"] == "-1")
        { 
            $order->addStatusToHistory('tuna_Cancelled', null, true);
            $order->setStatus('tuna_Cancelled');
            $order->save();
        }  else
        {
            switch ($status.'') {
                case '4':
                case '-1':
                case '6':
                case 'N':
                case 'A':
                case 'B':
                case 'E':
                    $order->addStatusToHistory('tuna_Cancelled', null, true);
                    $order->setStatus('tuna_Cancelled');
                    $order->save();
                    echo print_r("OK");
                    break;
                case '5':
                case '7':
                case '3':
                    $order->addStatusToHistory('tuna_Refunded', null, true);
                    $order->setStatus('tuna_Refunded');
                    $order->save();
                    echo print_r("OK");
                    break;
                case '8':
                case '9':
                case '2': 
                    $order->addStatusToHistory('tuna_Captured', null, true);
                    $order->setStatus('tuna_Captured');
                    $order->save();
                    echo print_r("OK");
                    break;
                case '0':
                case '1':				
                case 'C':
                case 'P':
                case '1':
                case '0':
                case 'D':	
                default:			
                    echo print_r("WAIT");
                    break;
 
          }
        } 
		exit;
    }
    private function session()
    {
        return (object)$this->_objectManager->create('Magento\Framework\Session\SessionManager')->getData(
            'tuna_payment'
        );
    } 
}
