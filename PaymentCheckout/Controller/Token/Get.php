<?php
namespace Tuna\PaymentCheckout\Controller\Token;

class Get extends \Magento\Framework\App\Action\Action  
{
    protected $_pageFactory;
    protected $_session;
    protected $curl;

    public function __construct(
		\Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\HTTP\Adapter\Curl $curl )
	{
        $this->_pageFactory = $pageFactory;
        $this->_session = $sessionManager;
        $this->curl = $curl;
     	return parent::__construct($context);
	}
   
	public function execute()
	{
        $post = '{}';
    
     
          try {   
  //  $ch = curl_init("https://5ec81c52155c130016a908a7.mockapi.io/Tuna/teste/1/InitPay");
  $apiOrderUrl = "https://5ec81c52155c130016a908a7.mockapi.io/Tuna/tt";
    $body = $post;
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($post)
    ];
    $this->curl->write('POST', $apiOrderUrl, $http_ver = '1.1', $headers, $post);
    //For Get Request
    #$this->curl->write('GET', $url);
    $response = $this->curl->read();
    echo $response;
        }
        catch (\Zend\Http\Exception\RuntimeException $runtimeException) 
        {
            echo $runtimeException->getMessage();
        }
		#echo  $this->_session->getSessionId();
        exit;
    }
}