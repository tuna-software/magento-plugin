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
    protected $countryInformationAcquirer;
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
        PaymentHelper $helper,
        \Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformationAcquirer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        $this->tunaPaymentMethod = $helper->getMethodInstance(self::PAYMENT_METHOD_CODE);
        $this->countryInformationAcquirer = $countryInformationAcquirer;
    }
    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $url = 'https://token.construcodeapp.com/api/Token/NewSession';
        $countriesInfo = $this->countryInformationAcquirer->getCountriesInfo();
        $countries = [];

        foreach ($countriesInfo as $country) {
            // Get regions for this country:
            $regions = [];
    
            if ($availableRegions = $country->getAvailableRegions()) {
                foreach ($availableRegions as $region) {
                    $regions[] = [
                        'id'   => $region->getId(),
                        'code' => $region->getCode(),
                        'name' => $region->getName()
                    ];
                }
            }
    
            $countries[] = [
                'id' => $country->getId(),
                'abbreviation' => $country->getTwoLetterAbbreviation(),
                'name'   => $country->getFullNameLocale(),
                'regions' => $regions
            ];
        }

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $customerSessionID = "0";
        $customerSessionEmail = "";
        $billingAddresses = [];
        if ($customerSession->isLoggedIn()) {
            $customerSessionID = $customerSession->getCustomer()->getId() . '';
            $customerSessionEmail = $customerSession->getCustomer()->getEmail();
            foreach ($customerSession->getCustomer()->getAddresses() as $address) {
                $addressB  = preg_split("/(\r\n|\n|\r)/", $address["street"]);
                $numberB = "";
                if (sizeof($addressB) > 1) {
                    $numberB = $addressB[1];
                }
                $complementB = "";
                if (sizeof($addressB) > 2) {
                    $complementB = $addressB[2];
                }
                $countryName = "";
                
                if($address["country_id"] != null){
                    foreach ($countries as &$c) {
                        if($c["id"] == $address["country_id"]){
                            $countryName = $c["name"];
                        }
                    }
                }

                array_push($billingAddresses, [
                    "Street" => $addressB[0],
                    "Number" => $numberB,
                    "Complement" => $complementB,
                    "Neighborhood" => "",
                    "City" => $address["city"],
                    "State" => $address["region"],
                    "CountryID" => $address["country_id"] != null ? $address["country_id"] : "BR",
                    "CountryName" => $countryName,
                    "PostalCode" => $address["postcode"],
                    "Phone" => $address["telephone"]
                ]);
            }
        }
        $cItem = [
            "AppToken" => $this->scopeConfig->getValue('payment/tuna/appKey'),
            "PartnerID" => $this->scopeConfig->getValue('payment/tuna/partnerid') * 1,
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
                    'billingAddresses' => $billingAddresses,
                ]
            ],
            'tuna_payment' => $this->tunaPaymentMethod->getStandardCheckoutPaymentUrl(),
            'countries' => $countries,
        ];
        return $config;
    }
}
