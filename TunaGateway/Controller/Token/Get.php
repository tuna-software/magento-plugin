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
        \Magento\Framework\HTTP\Adapter\Curl $curl
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_session = $sessionManager;
        $this->curl = $curl;
        return parent::__construct($context);
    }

    public function execute()
    {
        $post = array(
            'session_id' =>  $this->_session->getSessionId()
        );


        try {
            $apiOrderUrl = "http://tuna.mypig.com.br/home/index";
            $body = $post;
            $headers = [];
            $this->curl->write('POST', $apiOrderUrl, $http_ver = '1.1', $headers, $post);
         
            $response = $this->curl->read();
            echo $response;
        } catch (\Zend\Http\Exception\RuntimeException $runtimeException) {
            echo $runtimeException->getMessage();
        }
        #echo  $this->_session->getSessionId();
        exit;
        
    }
}
