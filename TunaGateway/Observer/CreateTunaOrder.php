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
      #$url = 'http://host.docker.internal:45455/api/Payment/Init'; //pass dynamic url
      $url  = 'http://tuna.construcodeapp.com/api/Payment/Init';
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
          'Countrycode' => $shipping["country_id"],
          "SalesChannel" => "ECOMMERCE",
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
      
      $body = \Zend_Http_Response::extractBody($result);
      $response = $this->jsonHelper->jsonDecode($body);      
      switch ($response["code"].'') {
        case '0':
              $order->setStatus('tuna_Started');
            break;
        case '1':
              $order->setStatus('tuna_Authorized');
            break;
        case '2':
              $order->setStatus('tuna_Captured');
            break;
        case '3':
              $order->setStatus('tuna_Refunded');
            break;
        case '4':
              $order->setStatus('tuna_Denied');
            break;
        case '5':
              $order->setStatus('tuna_Cancelled');
            break;
        case '6':
              $order->setStatus('tuna_Expired');
            break;
        case '7':
              $order->setStatus('tuna_Chargeback');
            break;
        case '8':
              $order->setStatus('tuna_MoneyReceived');
            break;
        case '9':
              $order->setStatus('tuna_PartialCancel');
            break;
        case 'A':
              $order->setStatus('tuna_Error');
            break;
        case 'B':
              $order->setStatus('tuna_RedFlag');
            break;
        case 'C':
              $order->setStatus('tuna_PendingCapture');
            break;
        case 'D':
              $order->setStatus('tuna_PendingAntiFraud');
            break;
        case 'E':
              $order->setStatus('tuna_DeniedAntiFraud');
            break;
      }
     
      $order->save();
    }

    #return $this;
  }
}
