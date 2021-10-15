<?php

namespace Tuna\TunaGateway\Block;


class AntifraudResolver extends \Magento\Framework\View\Element\Template
{
    protected $_scopeConfig;
    const kondutoBaseScript = "var __kdt = __kdt || [];
        __kdt.push({'public_key': '{KondutoPublicKey}'});
    __kdt.push({'post_on_load': false});
    (function() {
    var kdt = document.createElement('script');
    kdt.id = 'kdtjs'; kdt.type = 'text/javascript';
    kdt.async = true;
    kdt.src = 'https://i.k-analytix.com/k.js';
    var s = document.getElementsByTagName('body')[0];
    s.parentNode.insertBefore(kdt, s);
    })()";

    const kondutoCheckoutScript = "
    var customerID = {KondutoCostumerID}; // define o ID do cliente   
    (function() {
        var period = 300;     
        var limit = 20 * 1e3;     
        var nTry = 0;     
        var intervalID = setInterval(function() { // loop para retentar o envio         
            var clear = limit/period <= ++nTry;         
            if ((typeof(Konduto) !== 'undefined') &&
                 (typeof(Konduto.setCustomerID) !== 'undefined')) {            
            window.Konduto.setCustomerID(customerID); // envia o ID para a Konduto             
            clear = true;         
        }         
        if (clear) {
            clearInterval(intervalID); 
        }     
    }, period);
     })(customerID);";

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_scopeConfig = $scopeConfigInterface;
    }

    public function getCheckoutAntifraudScripts()
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $userID = 0;
        $resultingScript = "";

        if ($customerSession->isLoggedIn()) {
            $userID = $customerSession->getCustomer()->getId() . '';
        }
        $antifraudConfig = json_decode($this->_scopeConfig->getValue('payment/tuna_payment/options/antifraudConfig'));
        if($antifraudConfig->UseKonduto){
            $resultingScript = str_replace(["{KondutoCostumerID}"], [$userID], self::kondutoCheckoutScript);
        }
        return $resultingScript;
    }

    public function getAntifraudScripts()
    {
        $antifraudConfig = json_decode($this->_scopeConfig->getValue('payment/tuna_payment/options/antifraudConfig'));
        $resultingScript = "";
        if($antifraudConfig->UseKonduto){
            $resultingScript = str_replace(["{KondutoPublicKey}"], [$antifraudConfig->KondutoPublicKey], self::kondutoBaseScript);
        }
        return $resultingScript;
    }

}
