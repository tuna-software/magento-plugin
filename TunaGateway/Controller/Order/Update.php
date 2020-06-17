<?php

namespace Tuna\TunaGateway\Controller\Token;

class Get extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $_session;
    protected $curl;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        return parent::__construct($context);
    }

    public function execute()
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
           echo '<pre>';print_r($response["code"]); 
        exit;
        
    }
}
