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
        $url = 'http://tuna.mypig.com.br/home/index'; //pass dynamic url
        $requstbody = 'session_id=' . $this->_session->getSessionId() .
            '&appKey=' . $this->scopeConfig->getValue('payment/tuna/appKey') .
            '&partnerAccount=' . $this->scopeConfig->getValue('payment/tuna/partner_account');

        /* Create curl factory */
        $httpAdapter = $this->curlFactory->create();
        /* Forth parameter is POST body */
        $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', [], $requstbody);
        $result = $httpAdapter->read();
        $body = \Zend_Http_Response::extractBody($result);
        /* convert JSON to Array */
        $response = $this->jsonHelper->jsonDecode($body);

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $tunaSessionID = $response["code"];

        $response = null;
        if ($customerSession->isLoggedIn()) {
            $url = 'http://tuna.mypig.com.br/Card/List';
            $requestbody = 'SessionId=' . $tunaSessionID .
                '&CustomerId=' . $customerSession->getCustomer()->getId();

            $httpAdapter = $this->curlFactory->create();
            $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', [], $requestbody);
            $result = $httpAdapter->read();
            $body = \Zend_Http_Response::extractBody($result);
            $response = $this->jsonHelper->jsonDecode($body);
        }

        $config = [
            'payment' => [
                'tunagateway' => [
                    'sessionid' =>  $tunaSessionID,
                    'appKey' => $this->scopeConfig->getValue('payment/tuna/appKey'),
                    'partner_account' => $this->scopeConfig->getValue('payment/tuna/partner_account'),
                    'savedCreditCards' => ($response <> null && $response["Code"] == 1) ? $response["Tokens"] : null,
                    'is_user_logged_in' => $customerSession->isLoggedIn()
                ]
                ],
                'tuna_payment' => $this->tunaPaymentMethod->getStandardCheckoutPaymentUrl(),
        ];
        return $config;
    }
}
