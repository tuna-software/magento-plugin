<?php
namespace Tuna\TunaGateway\Model\Payment;
use \Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
 
class TunaProvider implements ConfigProviderInterface
{
/**
* @var \Magento\Framework\App\Config\ScopeConfigInterface
*/
protected $scopeConfig;
protected $_session;
/**
* first config value config path
*/
const CONFIG_KEY = 'payment/tunagateway/tokenid';
/**
* @param ScopeConfig $scopeConfig
*/
public function __construct(
ScopeConfig $scopeConfig ,
\Magento\Framework\Session\SessionManager $sessionManager,
\Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
\Magento\Framework\Json\Helper\Data $jsonHelper
) {
$this->scopeConfig = $scopeConfig;
$this->_session = $sessionManager;
$this->curlFactory = $curlFactory;
$this->jsonHelper = $jsonHelper;
}
/**
* {@inheritdoc}
*/
public function getConfig()
{
    $url = 'http://tuna.mypig.com.br/home/index'; //pass dynamic url
        $requstbody ='session_id='.$this->_session->getSessionId();
           
           /* Create curl factory */
           $httpAdapter = $this->curlFactory->create();
           /* Forth parameter is POST body */
           $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', [],$requstbody);
           $result = $httpAdapter->read();
           $body = \Zend_Http_Response::extractBody($result);
           /* convert JSON to Array */
           $response = $this->jsonHelper->jsonDecode($body);
           
$config = [
'payment' => [
'tunagateway' => [
'tokenid' => $response["code"]
]
]
];
return $config;
}
}