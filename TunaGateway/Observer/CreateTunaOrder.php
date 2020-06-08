<?php

namespace Tuna\TunaGateway\Observer;
use Magento\Framework\Event\ObserverInterface;

class CreateTunaOrder implements ObserverInterface
{
  protected $_scopeConfig;

  public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
    \Magento\Framework\Session\SessionManager $sessionManager
) {
    $this->_scopeConfig = $scopeConfigInterface;
    $this->_session = $sessionManager;
}
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
        $order = $observer->getEvent()->getOrder();

        //verify transaction
        if ($order->getStatus() == 'tuna_Started') {
            $orderId = $order->getId();
            $shipping = $order->getShippingAddress();
            $billing = $order->getBillingAddress();
            $payment = $order->getPayment();
            $itens = $order->getAllItems();
            $custormer = $order->getCustomerName();
            
        #    $data->AppToken=$_scopeConfig->getValue('payment/tuna/appkey');
       #     $data->PartnerUniqueID=$order->getId();
             $data_post = '{                
                "PaymentItems": {
                  "Items": [
                    {
                      "Amount": 200,
                      "ProductDescription": "item1",
                      "ItemQuantity": 2,
                      "CategoryName": "Category name",
                      "AntiFraud": {
                        "Ean": "A-121321"
                      }
                    }
                  ]
                },
                "PaymentData": {
                  "PaymentMethods": [
                    {
                      "PaymentMethodType": "1",
                      "Amount": 200,
                      "Installments": 1,
                      "CardInfo": {
                        "CardNumber": null,
                        "CardHolderName": "Daniel Morais",
                        "CVV": 841,
                        "BrandName": "Master",
                        "ExpirationMonth": null,
                        "ExpirationYear": null,
                        "Token": "11e02526-e82c-4ac1-b509-fa459b205bfd",
                        "TokenSingleUse": 0,
                        "SaveCard": false,
                        "BillingInfo": {
                          "Document": "481.427.300-22",
                          "DocumentType": "CPF",
                          "Address": {
                            "Street": "Ses Av. Das Nações",
                            "Number": "Q811",
                            "Complement": "Bloco G",
                            "Neighborhood": "Brasilia",
                            "City": "Brasilia",
                            "State": "DF",
                            "Country": "BR",
                            "PostalCode": "70429-900",
                            "Phone": "(61) 324 421 21"
                          }
                        }
                      }
                    }
                  ]
                
              }';

              $address  = preg_split("/(\r\n|\n|\r)/", $shipping["street"]);
              $number = "";
              if (sizeof($address)>1)
              {
                $number = $address[1];
              }
              $complement="";
              if (sizeof($address)>2)
              {
                $complement = $address[2];
              }
              $url = 'http://tuna.mypig.com.br/home/index'; //pass dynamic url
              $requstbody = [
                'AppToken'=>$this->_scopeConfig->getValue('payment/tuna/appKey'),
                'Account'=>$this->_scopeConfig->getValue('payment/tuna/partner_account'),
                'PartnerUniqueID'=> $orderId,
                'PartnerID'=> 1,
                'Customer'=> [
                  'Email'=> $billing["email"],
                  'Name'=> $billing["firstname"]." ".$billing["lastname"],
                  'ID'=> "",$this->getCustomerId(),#parent::getCustomerId()
                  'Document'=> "257.536.399-33",
                  'DocumentType'=> "CPF"
                ],
                'Countrycode'=> $shipping["country_id"],
                "SalesChannel"=> "ECOMMERCE",
                "AntiFraud"=>[
                  "DeliveryAddressee"=> $shipping["firstname"]
                ],
                "DeliveryAddress"=> [
                  "Street"=> $address[0],
                  "Number"=> $number,
                  "Complement"=> $complement,
                  "Neighborhood"=> "",
                  "City"=> $shipping["city"],
                  "State"=> $shipping["region"],
                  "Country"=> $shipping["country_id"],
                  "PostalCode"=> $shipping["postcode"],
                  "Phone"=> $shipping["telephone"]                
                  ],
                  "FrontData"=> [
                    "SessionID"=> $this->_session->getSessionId(),
                    "Origin"=> "WEBSITE",
                    "IpAddress"=> "",#$this->getRemoteIp(),#parent::getCustomerId()
                    "CookiesAccepted"=> true
                  ],
                  "ShippingItems"=> [
                    "Items"=> [[
                        "Type"=> "intelipost_intelipost_2",
                        "Amount"=> 45.89,
                        "Code"=> "34567"
                      ]]
                  ]
              ];
    
              /* Create curl factory */
              $httpAdapter = $this->curlFactory->create();
              /* Forth parameter is POST body */
              $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', ["Content-Type:application/json"],json_encode($requstbody));
              
              $result = $httpAdapter->read();
              $body = \Zend_Http_Response::extractBody($result);
              /* convert JSON to Array */
              $response = $this->jsonHelper->jsonDecode($body);
      
              $config = [
                  'payment' => [
                      'tunagateway' => [
                          'tokenid' => $response["code"]
                      ]
                  ]
              ];
            #tmp error returned!
            #order->cancel();
            //save order in orders table
            // $this->saveOrderAndEnvironment($orderId, $environment);

            // //$this->getEnvironmentName($environment);
            // $this->updateSalesOrderGridEnvironment($orderId, $environment);
        }

        return $this;
	}
}