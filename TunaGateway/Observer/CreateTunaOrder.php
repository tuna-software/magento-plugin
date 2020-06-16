<?php

namespace Tuna\TunaGateway\Observer;

use Magento\Framework\Event\ObserverInterface;


class CreateTunaOrder implements ObserverInterface
{
  protected $_scopeConfig;

  public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
    \Magento\Framework\Session\SessionManager $sessionManager,
    \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
    \Magento\Framework\Json\Helper\Data $jsonHelper
  ) {
    $this->_scopeConfig = $scopeConfigInterface;
    $this->_session = $sessionManager;
    $this->curlFactory = $curlFactory;
    $this->jsonHelper = $jsonHelper;
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



      $payItens = $order->getPaymentsCollection()->getItems();

      $custormerID = 0;
      $isNewCustomer = !$order->getCustomerId();
      if (!$isNewCustomer) {
        $custormerID = $order->getCustomerId();
      }
      $itens = [];
      $itemsCollection = $order->getAllVisibleItems();
      foreach ($itemsCollection as $item) {

        $cItem = [[
          "Amount" => $item->getPrice(),
          "ProductDescription" => $item->getProduct()->getName(),
          "ItemQuantity" => $item->getQtyToInvoice(),
          "CategoryName" => $item->getProductType(),
          "AntiFraud" => [
            "Ean" => $item->getSku()
          ]
        ]];
        $itens = array_merge($itens, $cItem);
      }
      $address  = preg_split("/(\r\n|\n|\r)/", $shipping["street"]);
      $number = "";
      if (sizeof($address) > 1) {
        $number = $address[1];
      }
      $complement = "";
      if (sizeof($address) > 2) {
        $complement = $address[2];
      }
      $addressB  = preg_split("/(\r\n|\n|\r)/", $billing["street"]);
      $numberB = "";
      if (sizeof($addressB) > 1) {
        $numberB = $addressB[1];
      }
      $complementB = "";
      if (sizeof($addressB) > 2) {
        $complementB = $addressB[2];
      }
      $documentType = "CPF";
      if (strlen($payment->getAdditionalInformation()["credit_card_document"]) > 17) {
        $documentType = "CNPJ";
      }
      $url = 'http://host.docker.internal:45455/api/Payment/Init'; //pass dynamic url
      $requstbody = [
        'AppToken' => $this->_scopeConfig->getValue('payment/tuna/appKey'),
        'Account' => $this->_scopeConfig->getValue('payment/tuna/partner_account'),
        'PartnerUniqueID' => $orderId,
        'PartnerID' => 1,
        'Customer' => [
          'Email' => $billing["email"],
          'Name' => $billing["firstname"] . " " . $billing["lastname"],
          'ID' => $custormerID,
          'Document' => $payment->getAdditionalInformation()["credit_card_document"],
          'DocumentType' => $documentType
        ],
        'Countrycode' => $shipping["country_id"],
        "SalesChannel" => "ECOMMERCE",
        "AntiFraud" => [
          "DeliveryAddressee" => $shipping["firstname"]
        ],
        "DeliveryAddress" => [
          "Street" => $address[0],
          "Number" => $number,
          "Complement" => $complement,
          "Neighborhood" => "",
          "City" => $shipping["city"],
          "State" => $shipping["region"],
          "Country" => $shipping["country_id"],
          "PostalCode" => $shipping["postcode"],
          "Phone" => $shipping["telephone"]
        ],
        "FrontData" => [
          "SessionID" => $this->_session->getSessionId(),
          "Origin" => "WEBSITE",
          "IpAddress" => $order->getRemoteIp(),
          "CookiesAccepted" => true
        ],
        "ShippingItems" => [
          "Items" => [[
            "Type" => $order->getShippingDescription(),
            "Amount" => $order->getBaseShippingAmount(),
            "Code" =>  $order->getShippingMethod(true)['carrier_code']
          ]]
        ],
        "PaymentItems" => [
          "Items" => $itens
        ],
        "PaymentData" => [
          "PaymentMethods" => [
            [
              "PaymentMethodType" => "1",
              "Amount" => $order->getGrandTotal(),
              "Installments" => 1,
              "CardInfo" => [
                "CardNumber" => $payment->getAdditionalInformation()["credit_card_hash"],
                "CardHolderName" => $payment->getAdditionalInformation()["credit_card_holder_name"],
                "CVV" => null,
                "BrandName" => "",
                "ExpirationMonth" => null,
                "ExpirationYear" => null,
                "Token" => $payment->getAdditionalInformation()["credit_card_token"],
                "TokenSingleUse" => 0,
                "SaveCard" => true,
                "BillingInfo" => [
                  "Document" => $payment->getAdditionalInformation()["credit_card_document"],
                  "DocumentType" => $documentType,
                  "Address" => [
                    "Street" => $addressB[0],
                    "Number" => $numberB,
                    "Complement" => $complementB,
                    "Neighborhood" => $billing["telephone"],
                    "City" => $billing["city"],
                    "State" => $billing["region"],
                    "Country" => $billing["country_id"],
                    "PostalCode" => $billing["postcode"],
                    "Phone" => $billing["telephone"]
                  ]
                ]
              ]
            ]
          ]
        ]
      ];

      /* Create curl factory */
      $httpAdapter = $this->curlFactory->create();
      $bodyJsonRequest = json_encode($requstbody);
      $filename = "/var/www/html/app/code/Tuna/newfile.txt";
      $file = fopen($filename, "a");

      if ($file == false) {
        echo ("Error in opening new file");
        exit();
      }
      fwrite($file, $bodyJsonRequest . " | " . date("j/n/Y h:i:s") . "\n");
      fclose($file);
      $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', ["Content-Type:application/json"], $bodyJsonRequest);

      $result = $httpAdapter->read();
      #$client = new \Zend_Http_Client($url);
      #$result =  $client->setRawData($bodyJsonRequest, null)->request('POST');
      $body = \Zend_Http_Response::extractBody($result);
      // $response = $this->jsonHelper->jsonDecode($body);

      // $config = [
      //     'payment' => [
      //         'tunagateway' => [
      //             'tokenid' => $response["code"]
      //         ]
      //     ]
      // ];
      #tmp error returned!
      #order->cancel();
      //save order in orders table
      // $this->saveOrderAndEnvironment($orderId, $environment);

      // //$this->getEnvironmentName($environment);
      // $this->updateSalesOrderGridEnvironment($orderId, $environment);
      $order->setStatus('complete');
      $order->save();
    }

    #return $this;
  }
}
