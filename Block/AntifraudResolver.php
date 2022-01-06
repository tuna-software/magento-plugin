<?php

namespace Tuna\TunaGateway\Block;

use Magento\Framework\Session\SessionManagerInterface as CoreSession;

class AntifraudResolver extends \Magento\Framework\View\Element\Template
{
    protected $_scopeConfig;
    const kondutoBaseScript = "var __kdt = __kdt || [];
        __kdt.push({'public_key': '{KondutoPublicKey}'});
    (function() {
    var kdt = document.createElement('script');
    kdt.id = 'kdtjs'; kdt.type = 'text/javascript';
    kdt.async = true;
    kdt.src = 'https://i.k-analytix.com/k.js';
    var s = document.getElementsByTagName('body')[0];
    s.parentNode.insertBefore(kdt, s);
    })()";

    const kondutoCheckoutScript = "
    var customerID = '{KondutoCostumerID}'; // define o ID do cliente
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

    const siftScript = "
    var _user_id = '{userID}' || ''; // Set to the user's ID, username, or email address, or '' if not yet known.
    var _session_id = '{sessionID}'; // Set to a unique session ID for the visitor's current browsing session.
  
    var _sift = window._sift = window._sift || [];
    _sift.push(['_setAccount', '{accountKey}']);
    _sift.push(['_setUserId', _user_id]);
    _sift.push(['_setSessionId', _session_id]);
    _sift.push(['_trackPageview']);
  
   (function() {
     function ls() {
       var e = document.createElement('script');
       e.src = 'https://cdn.sift.com/s.js';
       document.body.appendChild(e);
     }
     if (window.attachEvent) {
       window.attachEvent('onload', ls);
     } else {
       window.addEventListener('load', ls, false);
     }
   })();";

    protected $_coreSession;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        CoreSession $coreSession,
        \Magento\Framework\Session\SessionManager $sessionManager
    ) {
        parent::__construct($context, $data);
        $this->_scopeConfig = $scopeConfigInterface;
        $this->_coreSession = $coreSession;
        $this->_sessionManager = $sessionManager;
    }

    private function GUIDv4($trim = true)
    {
        // Windows
        if (function_exists('com_create_guid') === true) {
            if ($trim === true)
                return trim(com_create_guid(), '{}');
            else
                return com_create_guid();
        }

        // OSX/Linux
        if (function_exists('openssl_random_pseudo_bytes') === true) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        // Fallback (PHP 4.2+)
        mt_srand((float)microtime() * 10000);
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45);                  // "-"
        $lbrace = $trim ? "" : chr(123);    // "{"
        $rbrace = $trim ? "" : chr(125);    // "}"
        $guidv4 = $lbrace .
            substr($charid,  0,  8) . $hyphen .
            substr($charid,  8,  4) . $hyphen .
            substr($charid, 12,  4) . $hyphen .
            substr($charid, 16,  4) . $hyphen .
            substr($charid, 20, 12) .
            $rbrace;
        return $guidv4;
    }
    private function getCustomerID()
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $this->_coreSession->start();

        if ($customerSession->isLoggedIn()) {
            $costumerID = $customerSession->getCustomer()->getId() . '';
            $this->_coreSession->setCostumerID($costumerID);
        } else if ($this->_coreSession->getCostumerID()) {
            return $this->_coreSession->getCostumerID();
        } else {
            $costumerID = $this->GUIDv4();
            $this->_coreSession->setCostumerID($costumerID);
        }

        return $costumerID;
    }

    function IsNullOrEmptyString($str)
    {
        return (!isset($str) || trim($str) === '');
    }

    public function getCheckoutAntifraudScripts()
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $userID = $this->getCustomerID();
        $resultingScript = "";

        $antifraudConfigText = $this->_scopeConfig->getValue('payment/tuna_payment/options/antifraudConfig');
        if ($this->IsNullOrEmptyString($antifraudConfigText))
            return '';
        $antifraudConfig = json_decode($antifraudConfigText);
        if ($antifraudConfig->UseKonduto) {
            $resultingScript = str_replace(["{KondutoCostumerID}"], [$userID], self::kondutoCheckoutScript);
        }
        return $resultingScript;
    }

    public function getAntifraudScripts()
    {
        $antifraudConfigText = $this->_scopeConfig->getValue('payment/tuna_payment/options/antifraudConfig');
        if ($this->IsNullOrEmptyString($antifraudConfigText))
            return '';
        $antifraudConfig = json_decode($antifraudConfigText);
        $resultingScript = "";
        if ($antifraudConfig->UseKonduto) {
            $resultingScript .= str_replace(["{KondutoPublicKey}"], [$antifraudConfig->KondutoPublicKey], self::kondutoBaseScript);
        }
        if ($antifraudConfig->UseSift) {
            $userID = $this->getCustomerID();
            $sessionID = $this->_session->getSessionId();
            $resultingScript .= str_replace(["{accountKey}", "{userID}", "{sessionID}"], [$antifraudConfig->SiftAccountKey, $userID, $sessionID], self::siftScript);
        }
        return $resultingScript;
    }
}
