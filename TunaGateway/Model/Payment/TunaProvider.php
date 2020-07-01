<?php

namespace Tuna\TunaGateway\Model\Payment;

use \Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Payment\Helper\Data as PaymentHelper;

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
    const PAYMENT_METHOD_CODE = 'tuna';
    /**
     * @param ScopeConfig $scopeConfig
     */
    public function __construct(
        ScopeConfig $scopeConfig,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        PaymentHelper $helper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        $this->tunaPaymentMethod = $helper->getMethodInstance(self::PAYMENT_METHOD_CODE);
    }
    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $url = 'https://token.construcodeapp.com/api/Token/NewSession'; 

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $customerSessionID="0";        
        $customerSessionEmail="";
        if ($customerSession->isLoggedIn()) 
        {
            $customerSessionID=$customerSession->getCustomer()->getId().'';        
            $customerSessionEmail=$customerSession->getCustomer()->getEmail();;
        }
        $cItem = [
            "AppToken" =>$this->scopeConfig->getValue('payment/tuna/appKey'),
            "PartnerID" => $this->scopeConfig->getValue('payment/tuna/partnerid')*1,
            "Customer" => [
                "Email" => $customerSessionEmail,
                "ID" => $customerSessionID,
            ]
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
        $tunaSessionID = $response["sessionId"];

        $response = null;
        if ($tunaSessionID <> null && $customerSession->isLoggedIn()) {
            $url = 'https://token.construcodeapp.com/api/Token/List';
            $cItem = [
                "SessionId" => $tunaSessionID
                ];
            $bodyJsonRequest = json_encode($cItem);
       
            $httpAdapter = $this->curlFactory->create();
            $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1',  ["Content-Type:application/json"], $bodyJsonRequest);
            $result = $httpAdapter->read();
            $body = \Zend_Http_Response::extractBody($result);
            $response = $this->jsonHelper->jsonDecode($body);
        }
    
        $config = [
            'payment' => [
                'tunagateway' => [
                    'sessionid' =>  $tunaSessionID,
                    'savedCreditCards' => ($response <> null && $response["code"] == 1) ? $response["tokens"] : null,
                    'is_user_logged_in' => $customerSession->isLoggedIn(),
                    'allow_boleto' => $this->scopeConfig->getValue('payment/tuna/allow_boleto'),
                    ]
                ],
                'tuna_payment' => $this->tunaPaymentMethod->getStandardCheckoutPaymentUrl(),
        ];
        return $config;
    }
}
