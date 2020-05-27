<?php
namespace Tuna\TunaGateway\Model\Payment;
use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
 
class TunaProvider implements ConfigProviderInterface
{
/**
* @var \Magento\Framework\App\Config\ScopeConfigInterface
*/
protected $scopeConfig;
/**
* first config value config path
*/
const CONFIG_KEY = 'payment/tunagateway/tokenid';
/**
* @param ScopeConfig $scopeConfig
*/
public function __construct(
ScopeConfig $scopeConfig
) {
$this->scopeConfig = $scopeConfig;
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