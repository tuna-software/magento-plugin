<?php

namespace Tuna\TunaGateway\Observer;

use Magento\Framework\Event\ObserverInterface;


class CreateTunaOrder implements ObserverInterface
{
  protected $_scopeConfig;
  protected $_code = "tuna";
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
      $tokenSessionParam= $payment->getAdditionalInformation()["session_id"];
  
      $fullName =  $billing["firstname"] . " " . $billing["lastname"];
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
      $numberB = "1";
      if (sizeof($addressB) > 1) {
        $numberB = $addressB[1];
      }
      $complementB = "";
      if (sizeof($addressB) > 2) {
        $complementB = $addressB[2];
      }


      $documentType = "CPF";
      if (strlen($payment->getAdditionalInformation()["buyer_document"]) > 17) {
        $documentType = "CNPJ";
      }
      $PaymentMethodType = "1";
      $cardInfo = null;
      $boletoInfo = null;
      if ($payment->getAdditionalInformation()["is_boleto_payment"]=="false"){
      $cardInfo = [
        "TokenProvider" => "Tuna",
        "CardNumber" => "",
        "CardHolderName" => $payment->getAdditionalInformation()["buyer_name"],
        "CVV" => $payment->getAdditionalInformation()["credit_card_cvv"],
        "BrandName" => $payment->getAdditionalInformation()["credit_card_brand"],
        "ExpirationMonth" =>  $payment->getAdditionalInformation()["credit_card_expiration_month"]*1,
        "ExpirationYear" =>  $payment->getAdditionalInformation()["credit_card_expiration_year"]*1,
        "Token" => $payment->getAdditionalInformation()["credit_card_token"],
        "TokenSingleUse" => 0,
        "SaveCard" => true,
        "BillingInfo" => [
          "Document" => $payment->getAdditionalInformation()["buyer_document"],
          "DocumentType" => $documentType,
          "Address" => [
            "Street" => $addressB[0],
            "Number" => $numberB,
            "Complement" => $complementB,
            "Neighborhood" => "Centro",
            "City" => $billing["city"],
            "State" => $this->getStateCode($billing["region"]),
            "Country" => $billing["country_id"]!=null?$billing["country_id"]:"BR",
            "PostalCode" => $billing["postcode"],
            "Phone" => $billing["telephone"]
          ]
        ]
          ];
        }else
        {
          $PaymentMethodType = "3";
          $boletoInfo = [
              "BillingInfo" => [
              "Document" => $payment->getAdditionalInformation()["buyer_document"],
              "DocumentType" => $documentType,
              "Address" => [
                "Street" => $addressB[0],
                "Number" => $numberB,
                "Complement" => $complementB,
                "Neighborhood" => $complementB!=""?$complementB:"Centro",
                "City" => $billing["city"],
                "State" =>  $this->getStateCode($billing["region"]),
                "Country" => $billing["country_id"]!=null?$billing["country_id"]:"BR",
                "PostalCode" => $billing["postcode"],
                "Phone" => $billing["telephone"]
              ]
            ]
              ];

        }
      #$url = 'http://host.docker.internal:45457/api/Payment/Init'; //pass dynamic url
      $url  = 'http://tuna.construcodeapp.com/api/Payment/Init';
      $requstbody = [
        'AppToken' => $this->_scopeConfig->getValue('payment/tuna/appKey'),
        'Account' => $this->_scopeConfig->getValue('payment/tuna/partner_account'),
        'PartnerUniqueID' => $orderId,
        'TokenSession' =>  $tokenSessionParam,
        'PartnerID' => $this->_scopeConfig->getValue('payment/tuna/partnerid')*1,
        'Customer' => [
          'Email' => $billing["email"],
          'Name' =>$fullName,
          'ID' => $custormerID,
          'Document' => $payment->getAdditionalInformation()["buyer_document"],
          'DocumentType' => $documentType
        ],
        "AntiFraud" => [
          "DeliveryAddressee" => $fullName
        ],
        "DeliveryAddress" => [
          "Street" => $address[0],
          "Number" => $number,
          "Complement" => $complement,
          "Neighborhood" => "",
          "City" => $shipping["city"],
          "State" => $this->getStateCode($shipping["region"]),
          "Country" => $shipping["country_id"]!=null?$shipping["country_id"]:"BR",
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
            "Code" =>  ""
          ]]
        ],
        "PaymentItems" => [
          "Items" => $itens
        ],
        "PaymentData" => [
          'Countrycode' => $billing["country_id"]!=null?$billing["country_id"]:"BR",
          "SalesChannel" => "ECOMMERCE",
          "PaymentMethods" => [
            [
              "PaymentMethodType" => $PaymentMethodType,
              "Amount" => $order->getGrandTotal(),
              "Installments" => 1,
              "CardInfo" => $cardInfo,
              "BoletoInfo" => $boletoInfo
            ]
          ]
        ]
      ];

      /* Create curl factory */
      $httpAdapter = $this->curlFactory->create();
      $bodyJsonRequest = json_encode($requstbody);
      #$this->saveLog($bodyJsonRequest);
      $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', ["Content-Type:application/json"], $bodyJsonRequest);

      $result = $httpAdapter->read();
      
      $body = \Zend_Http_Response::extractBody($result);
      
      try{
        $response = $this->jsonHelper->jsonDecode($body);
        switch (strval($response["status"])) {
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
          case '-1':
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
        if (strval($response["code"])=="1" && $response["status"]=="C")
        {
          if ($payment->getAdditionalInformation()["is_boleto_payment"]=="true")
          {
            if ($response["methods"]!=null && $response["methods"][0]["redirectInfo"]!=null){
              $additionalData = $payment->getAdditionalInformation();
              $additionalData["boleto_url"] =$response["methods"][0]["redirectInfo"]["url"];    
              $payment->setData('additional_information',$additionalData);   
              $payment->save(); 
            }
          }
       }
      }catch(\Exception $e){
        $order->setStatus('tuna_Cancelled');
      }
      
      $order->save();
    }

    #return $this;
  }

  public function saveLog($txt)
  {
    $filename = "/var/www/html/app/code/Tuna/newfile.txt";
    $file = fopen($filename, "a");

    if ($file == false) {
      echo ("Error in opening new file");
      exit();
    }
    fwrite($file, $txt . " | " . date("j/n/Y h:i:s") . "\n");
    fclose($file);
  }

  public function getStateCode($state)
  {
    $code = "BA";
    switch ($state) {
      case 'Bahia':
        $code = "BA";
          break;
          case 'Acre':
            $code = "AC";
          break;
          case 'Alagoas':
            $code = "AL";
          break;
          case 'Amapá':
            $code = "AP";
          break;
          case 'Amazonas':
            $code = "AM";
          break;
          case 'Bahia':
            $code = "BA";
          break;
          case 'Ceará':
            $code = "CE";
          break;
          case 'Distrito Federal':
            $code = "DF";
          break;
          case 'Espírito Santo':
            $code = "ES";
          break;
          case 'Goiás':
            $code = "GO";
          break;
          case 'Maranhão':
            $code = "MA";
          break;
          case 'Mato Grosso':
            $code = "MT";
          break;
          case 'Mato Grosso do Sul':
            $code = "MS";
          break;
          case 'Minas Gerais':
            $code = "MG";
          break;
          case 'Pará':
            $code = "PA";
          break;
          case 'Paraíba':
            $code = "PB";
          break;
          case 'Paraná':
            $code = "PR";
          break;
          case 'Pernambuco':
            $code = "PE";
          break;
          case 'Piauí':
            $code = "PI";
          break;
          case 'Rio de Janeiro':
            $code = "RJ";
          break;
          case 'Rio Grande do Norte':
            $code = "RN";
          break;
          case 'Rio Grande do Sul':
            $code = "RS";
          break;
          case 'Rondônia':
            $code = "RO";
          break;
          case 'Roraima':
            $code = "RR";
          break;
          case 'Santa Catarina':
            $code = "SC";
          break;
          case 'São Paulo':
            $code = "SP";
          break;
          case 'Sergipe':
            $code = "SE";
          break;
          case 'Tocantins':
            $code = "TO";
          break;
    }
    return $code;
  }
}
