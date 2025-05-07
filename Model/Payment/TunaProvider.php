<?php

namespace Tuna\TunaGateway\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Framework\HTTP\Client\Curl;


class TunaProvider implements ConfigProviderInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Session\SessionManager
     */
    protected $_session;

    /**
     * @var string
     */
    protected $_tunaEndpointDomain;

    /**
     * @var \Magento\Directory\Api\CountryInformationAcquirerInterface
     */
    protected $countryInformationAcquirer;

    /**
     * @var CoreSession
     */
    protected $_coreSession;

    /**
     * @var \Magento\Framework\HTTP\Adapter\CurlFactory
     */
    protected $curlFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Payment\Model\MethodInterface
     */
    protected $tunaPaymentMethod;

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
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        PaymentHelper $helper,
        \Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformationAcquirer,
        CoreSession $coreSession
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        $this->checkoutSession = $checkoutSession;
        $this->tunaPaymentMethod = $helper->getMethodInstance(self::PAYMENT_METHOD_CODE);
        $this->countryInformationAcquirer = $countryInformationAcquirer;
        if ($this->scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') == 'production'){
            $this->_tunaEndpointDomain = 'tunagateway.com';
          }else{
            $this->_tunaEndpointDomain = 'tuna-demo.uy';
        }
        $this->_coreSession = $coreSession;
    }
    public function getFee($totalInstallments)
    {
        $feeList = [];

        for($i = 1; $i <= $totalInstallments; $i++){
            $feeInput = $this->scopeConfig->getValue('payment/tuna_payment/credit_card/p'.$i);
            $fee = is_numeric($feeInput) ? (float) $feeInput : 0;
            $feeList[$i - 1] = $fee;
        }

        return $feeList;
    }
    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $tunaSessionID = null;
        $url = 'https://token.' . $this->_tunaEndpointDomain . '/api/Token/NewSession';
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

            usort($regions, function($a, $b) {
                return strcmp($a["name"], $b["name"]);
            });

            $countries[] = [
                'id' => $country->getId(),
                'abbreviation' => $country->getTwoLetterAbbreviation(),
                'name'   => $country->getFullNameLocale(),
                'regions' => $regions
            ];
        }
        usort($countries, function ($a, $b) {
            $nameA = $a["name"] ?? '';
            $nameB = $b["name"] ?? '';
            return strcmp($nameA, $nameB);
        });

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');

        $this->_coreSession->start();
        $customerSessionID = $this->_coreSession->getCostumerID();

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
                    "City" => $address["city"],
                    "State" => $address["region"],
                    "CountryID" => $address["country_id"] != null ? $address["country_id"] : "BR",
                    "CountryName" => $countryName,
                    "PostalCode" => $address["postcode"],
                    "Phone" => $address["telephone"]
                ]);
            }
        }
        try {
            $cItem = [
                "AppToken" => $this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey'),
                "Customer" => [
                    "Email" => $customerSessionEmail,
                    "ID" => $customerSessionID,
                ]
            ];
            $bodyJsonRequest = json_encode($cItem);

            /* Use Curl client */
            $httpClient = new Curl();
            $httpClient->addHeader("Content-Type", "application/json");
            $httpClient->post($url, $bodyJsonRequest);
            $responseBody = $httpClient->getBody();

            /* Convert JSON to Array */
            $response = $this->jsonHelper->jsonDecode($responseBody);
            $tunaSessionID = $response["sessionId"];
        }catch(\Exception $e)
        {
        }
        $response = null;
        if ($tunaSessionID !== null && $customerSession->isLoggedIn()) {
            $url = 'https://token.' . $this->_tunaEndpointDomain . '/api/Token/List';
            $cItem = [
                "SessionId" => $tunaSessionID
            ];
            $bodyJsonRequest = json_encode($cItem);

            try {
                $httpClient = new Curl();
                $httpClient->addHeader("Content-Type", "application/json");
                $httpClient->post($url, $bodyJsonRequest);
                $responseBody = $httpClient->getBody();

                $response = $this->jsonHelper->jsonDecode($responseBody);
            } catch (\Exception $e) {
                // Handle exception
            }
        }

        $config = [
            'payment' => [
                'tunagateway' => [
                    'sessionid' =>  $tunaSessionID,
                    'useSandboxBundle' => $this->scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') != 'production',
                    'savedCreditCards' => ($response <> null && $response["code"] == 1) ? $response["tokens"] : null,
                    'is_user_logged_in' => $customerSession->isLoggedIn(),
                    'allow_boleto' => $this->scopeConfig->getValue('payment/tuna_payment/options/allow_boleto'),
                    'allow_crypto' => $this->scopeConfig->getValue('payment/tuna_payment/options/allow_crypto'),
                    'allow_pix' => $this->scopeConfig->getValue('payment/tuna_payment/options/allow_pix'),
                    'allow_link' => $this->scopeConfig->getValue('payment/tuna_payment/options/allow_link'),
                    'tuna_active' => $this->scopeConfig->getValue('payment/tuna_payment/active'),
                    'allow_card' => $this->scopeConfig->getValue('payment/tuna_payment/options/allow_card'),
                    'allow_pay_with_two_cards' => $this->scopeConfig->getValue('payment/tuna_payment/credit_card/allow_pay_with_two_cards'),
                    'minimum_installment_value' => $this->scopeConfig->getValue('payment/tuna_payment/credit_card/minimum_installment_value'),
                    'installments' =>  $this->scopeConfig->getValue('payment/tuna_payment/credit_card/installments'),
                    'feeList'=>$this->getFee($this->scopeConfig->getValue('payment/tuna_payment/credit_card/installments')),
                    'billingAddresses' => $billingAddresses,
                    'internalSessionID' =>  $this->_session->getSessionId(),
                    'title' => $this->scopeConfig->getValue('payment/tuna_payment/options/title'),
                    'title_credit' => $this->scopeConfig->getValue('payment/tuna_payment/options/title_credit'),
                    'title_boleto' => $this->scopeConfig->getValue('payment/tuna_payment/options/title_boleto'),
                    'title_pix' => $this->scopeConfig->getValue('payment/tuna_payment/options/title_pix'),
                    'title_link' => $this->scopeConfig->getValue('payment/tuna_payment/options/title_link'),
                    'title_crypto' => $this->scopeConfig->getValue('payment/tuna_payment/options/title_crypto')
                ]
            ],
            'tuna_payment' => $this->tunaPaymentMethod->getStandardCheckoutPaymentUrl(),
            'countries' => $countries,
        ];
        return $config;
    }
}
