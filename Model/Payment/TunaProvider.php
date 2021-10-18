<?php

namespace Tuna\TunaGateway\Model\Payment;

use \Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;


class TunaProvider implements ConfigProviderInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    protected $_session;
    protected $_tunaEndpointDomain;
    protected $countryInformationAcquirer;
    protected $_coreSession;

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
    
    public function getInstallment($valor_total,$totalInstallments)
    {
        $parcelas = array();
        for($i=1;$i<=$totalInstallments;$i++){
            $tmpJuros = $this->scopeConfig->getValue('payment/tuna_payment/credit_card/p'.$i);
            $juros = 0;
            if ($tmpJuros!='')
            {
                $juros = (float)$tmpJuros;
            }
            $option = '';
            if($juros==0){                        
                $option = $i.'x R$ '.number_format($valor_total/$i, 2, ",", ".").' (s/ juros) - R$ '.number_format($valor_total, 2, ",", ".");
            }else{                                
                $I =$juros/100.00;                
                $valor_parcela = $this->GetValorParcela( $this->scopeConfig->getValue('payment/tuna_payment/credit_card/fee_config'),$valor_total,$i,$I);
                $option = $i.'x R$ '.number_format($valor_parcela, 2, ",", ".").' (c/ juros) - R$ '.number_format($valor_parcela*$i, 2, ",", ".");                
            }
            $parcelas[$i-1] = $option;
        }
        return $parcelas;
    }
    public function GetValorParcela($tipo, $valorTotal,$parcela,$juros)
    {
        if ($tipo == 'S')
        {
            return ($valorTotal * (1 +$juros))/$parcela;
        }else
        {
            return ($valorTotal*pow((1+$juros),$parcela))/$parcela;
        }
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
        usort($countries, function($a, $b) {
            return strcmp($a["name"], $b["name"]);
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
        try{
            $cItem = [
                "AppToken" => $this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey'),
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
        }catch(\Exception $e)
        { 
        }
        $response = null;
        if ($tunaSessionID <> null && $customerSession->isLoggedIn()) {
            $url = 'https://token.' . $this->_tunaEndpointDomain . '/api/Token/List'; 
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
        $quote = $this->checkoutSession->getQuote();
        $total = $quote->getGrandTotal();
        $installmentOptions = $this->getInstallment($total,$this->scopeConfig->getValue('payment/tuna_payment/credit_card/installments'));
        $config = [
            'payment' => [
                'tunagateway' => [
                    'sessionid' =>  $tunaSessionID,
                    'useSandboxBundle' => $this->scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') != 'production',
                    'savedCreditCards' => ($response <> null && $response["code"] == 1) ? $response["tokens"] : null,
                    'is_user_logged_in' => $customerSession->isLoggedIn(),
                    'allow_boleto' => $this->scopeConfig->getValue('payment/tuna_payment/options/allow_boleto'),
                    'installments' =>  $installmentOptions,
                    'billingAddresses' => $billingAddresses,
                    'title' => $this->scopeConfig->getValue('payment/tuna_payment/options/title')
                ]
            ],
            'tuna_payment' => $this->tunaPaymentMethod->getStandardCheckoutPaymentUrl(),
            'countries' => $countries,
        ];
        return $config;
    }
}
