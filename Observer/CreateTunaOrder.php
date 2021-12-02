<?php

namespace Tuna\TunaGateway\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;



class CreateTunaOrder implements ObserverInterface
{
  protected $_scopeConfig;
  protected $_tunaEndpointDomain;
  protected $_code = "tuna";
  protected $_coreSession;

  public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
    \Magento\Framework\Session\SessionManager $sessionManager,
    \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
    \Magento\Framework\Json\Helper\Data $jsonHelper,
    CoreSession $coreSession
  ) {
    $this->_scopeConfig = $scopeConfigInterface;
    $this->_session = $sessionManager;
    $this->curlFactory = $curlFactory;
    $this->jsonHelper = $jsonHelper;
    if ($this->_scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') == 'production') {
      $this->_tunaEndpointDomain = 'engine.tunagateway.com';
    } else {
      $this->_tunaEndpointDomain = 'sandbox.tuna-demo.uy';
    }
    $this->_coreSession = $coreSession;
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
      $tokenSessionParam = $payment->getAdditionalInformation()["session_id"];

      $fullName =  $billing["firstname"] . " " . $billing["lastname"];
      $payItens = $order->getPaymentsCollection()->getItems();

      $this->_coreSession->start();
      $customerID = $this->_coreSession->getCostumerID();

      $isNewCustomer = !$order->getCustomerId();
      if (!$isNewCustomer) {
        $customerID = $order->getCustomerId();
      }
      $itens = [];

      $valorTotal = $this->getValorFinal($order->getGrandTotal(), $payment->getAdditionalInformation()["credit_card_installments"] * 1);
      $itemsCollection = $order->getAllVisibleItems();
      $tmpvalorTotalPendente = $valorTotal - $order->getBaseShippingAmount();
      foreach ($itemsCollection as $item) {
        $valorItem = $this->getValorFinal($item->getPrice(), $payment->getAdditionalInformation()["credit_card_installments"] * 1);
        $tmpvalorTotalPendente = $tmpvalorTotalPendente - ($valorItem * $item->getQtyToInvoice());
        $cItem = [[
          "Amount" =>  $valorItem,
          "ProductDescription" => $item->getProduct()->getName(),
          "ItemQuantity" => $item->getQtyToInvoice(),
          "CategoryName" => $item->getProductType(),
          "AntiFraud" => [
            "Ean" => $item->getSku()
          ]
        ]];
        $itens = array_merge($itens, $cItem);
      }
      $deliveryAddress = [];
      if (!empty($shipping) && isset($shipping["street"]) && isset($shipping["city"]) && isset($shipping["region"]) && isset($shipping["postcode"]) && isset($shipping["telephone"])) {
        $address = preg_split("/(\r\n|\n|\r)/", $shipping["street"]);
        $number = "";
        if (sizeof($address) > 1) {
          $number = $address[1];
        }
        $complement = "";
        if (sizeof($address) > 2) {
          $complement = $address[2];
        }
        $deliveryAddress = [
          "Street" => $address[0],
          "Number" => $number,
          "Complement" => $complement,
          "Neighborhood" => "",
          "City" => $shipping["city"],
          "State" => $this->getStateCode($shipping["region"]),
          "Country" => $shipping["country_id"] != null ? $shipping["country_id"] : "BR",
          "PostalCode" => $shipping["postcode"],
          "Phone" => $shipping["telephone"]
        ];
      }
      $numberB = "1";
      $complementB = "";
      $billingAddress = "";
      if (!empty($billing) && isset($billing["street"])) {
        $addressB  = preg_split("/(\r\n|\n|\r)/", $billing["street"]);
        $billingAddress = $addressB[0];
        if (sizeof($addressB) > 1) {
          $numberB = $addressB[1];
        }
        if (sizeof($addressB) > 2) {
          $complementB = $addressB[2];
        }
      }
      $documentType = "CPF";
      if (strlen($payment->getAdditionalInformation()["buyer_document"]) > 17) {
        $documentType = "CNPJ";
      }
      $PaymentMethodType = "1";
      $cardInfo = null;
      $boletoInfo = null;
      $installments = 1;
      if ($payment->getAdditionalInformation()["is_boleto_payment"] == "false") {
        if ($payment->getAdditionalInformation()["is_pix_payment"] == "false") {
          $installments = $payment->getAdditionalInformation()["credit_card_installments"] * 1;
          $order->addStatusHistoryComment( 'Pagamento em Cartão de crédito');
          $payment = $order->getPayment();
          $payment->setMethod('credit');
          $payment->save();
       
        $cardInfo = [
          "TokenProvider" => "Tuna",
          "CardNumber" => "",
          "CardHolderName" => $payment->getAdditionalInformation()["buyer_name"],
          "BrandName" => $payment->getAdditionalInformation()["credit_card_brand"],
          "ExpirationMonth" =>  $payment->getAdditionalInformation()["credit_card_expiration_month"] * 1,
          "ExpirationYear" =>  $payment->getAdditionalInformation()["credit_card_expiration_year"] * 1,
          "Token" => $payment->getAdditionalInformation()["credit_card_token"],
          "TokenSingleUse" => $isNewCustomer ? 1 : 0,
          "SaveCard" => false,
          "BillingInfo" => [
            "Document" => $payment->getAdditionalInformation()["buyer_document"],
            "DocumentType" => $documentType,
            "Address" => [
              "Street" => $billingAddress,
              "Number" => $numberB,
              "Complement" => $complementB,
              "Neighborhood" => "Centro",
              "City" => $billing["city"],
              "State" => $this->getStateCode($billing["region"]),
              "Country" => $billing["country_id"] != null ? $billing["country_id"] : "BR",
              "PostalCode" => $billing["postcode"],
              "Phone" => $billing["telephone"]
            ]
          ]
        ];
      }else
      {
        $PaymentMethodType = "D";	
        $payment = $order->getPayment();
          $payment->setMethod('pix');
          $payment->save();
        $order->addStatusHistoryComment( 'Pagamento em PIX');			
      }
      } else {
        $PaymentMethodType = "3";
        $payment = $order->getPayment();
          $payment->setMethod('boleto');
          $payment->save();
        $order->addStatusHistoryComment( 'Pagamento em Boleto');
        $boletoInfo = [
          "BillingInfo" => [
            "Document" => $payment->getAdditionalInformation()["buyer_document"],
            "DocumentType" => $documentType,
            "Address" => [
              "Street" => $billingAddress,
              "Number" => $numberB,
              "Complement" => $complementB,
              "Neighborhood" => $complementB != "" ? $complementB : "Centro",
              "City" => $billing["city"],
              "State" =>  $this->getStateCode($billing["region"]),
              "Country" => $billing["country_id"] != null ? $billing["country_id"] : "BR",
              "PostalCode" => $billing["postcode"],
              "Phone" => $billing["telephone"]
            ]
          ]
        ];
      }

      $url  = 'https://' . $this->_tunaEndpointDomain . '/api/Payment/Init';
      $requstbody = [
        'AppToken' => $this->_scopeConfig->getValue('payment/tuna_payment/credentials/appKey'),
        'Account' => $this->_scopeConfig->getValue('payment/tuna_payment/credentials/partner_account'),
        'PartnerUniqueID' => $orderId,
        'TokenSession' =>  $tokenSessionParam,
        'Customer' => [
          'Email' => $billing["email"],
          'Name' => $fullName,
          'ID' => $customerID,
          'Document' => $payment->getAdditionalInformation()["buyer_document"],
          'DocumentType' => $documentType
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
            "Amount" => $order->getBaseShippingAmount() + $tmpvalorTotalPendente,
            "Code" =>  ""
          ]]
        ],
        "PaymentItems" => [
          "Items" => $itens
        ],
        "PaymentData" => [
          'Countrycode' => $billing["country_id"] != null ? $billing["country_id"] : "BR",          
          "AntiFraud" => [
            "DeliveryAddressee" => $fullName
          ],
          "DeliveryAddress" => $deliveryAddress,
          "SalesChannel" => "ECOMMERCE",
          "PaymentMethods" => [
            [
              "PaymentMethodType" => $PaymentMethodType,
              "Amount" => $valorTotal,
              "Installments" => $installments,
              "CardInfo" => $cardInfo,
              "BoletoInfo" => $boletoInfo
            ]
          ]
        ]
      ];

      /* Create curl factory */
      $httpAdapter = $this->curlFactory->create();
      $bodyJsonRequest = json_encode($requstbody);
      //$this->saveLog($bodyJsonRequest);
      $httpAdapter->write(\Zend_Http_Client::POST, $url, '1.1', ["Content-Type:application/json"], $bodyJsonRequest);

      $result = $httpAdapter->read();

      $body = \Zend_Http_Response::extractBody($result);
      //$this->saveLog($body);
      try {
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
          case 'P':
            $order->setStatus('tuna_PendingCapture');
            break;
          case 'D':
            $order->setStatus('tuna_PendingAntiFraud');
            break;
          case 'E':
            $order->setStatus('tuna_DeniedAntiFraud');
            break;
        }
        if (strval($response["code"]) == "1" && ($response["status"] == "C" || $response["status"] == "P" )) {
          if ($payment->getAdditionalInformation()["is_boleto_payment"] == "true") {
            if ($response["methods"] != null && $response["methods"][0]["redirectInfo"] != null) {
              $additionalData = $payment->getAdditionalInformation();
              $additionalData["boleto_url"] = $response["methods"][0]["redirectInfo"]["url"];
              $payment->setData('additional_information', $additionalData);
              $payment->save();
            }
          }
          if ($payment->getAdditionalInformation()["is_pix_payment"] == "true") {
            if ($response["methods"] != null && $response["methods"][0]["pixInfo"] != null) {
              $additionalData = $payment->getAdditionalInformation();
              $additionalData["pix_image"] = $response["methods"][0]["pixInfo"]["qrImage"];
              $additionalData["pix_key"] = $response["methods"][0]["pixInfo"]["qrContent"];
              $payment->setData('additional_information', $additionalData);
              $payment->save();
            }
          } 
        }
      } catch (\Exception $e) {
        $order->setStatus('tuna_Cancelled');
      }
      if ($valorTotal != $order->getGrandTotal()){        
        $order->addStatusHistoryComment( 'Acréscimo de R$ '.number_format($valorTotal - $order->getGrandTotal(), 2, ",", ".").' em juros');
        $order->setGrandTotal($valorTotal);
      }
      $order->save();
    }

    #return $this;
  }

  public function getValorFinal($valorTotal, $parcela)
  {
    $tipo = $this->_scopeConfig->getValue('payment/tuna_payment/credit_card/fee_config');
    $tmpJuros = $this->_scopeConfig->getValue('payment/tuna_payment/credit_card/p' . $parcela);
    $juros = 0;
    if ($tmpJuros != '') {
      $juros = (float)$tmpJuros;
    }

    if ($juros == 0) {
      return $valorTotal;
    } else {
      $juros = $juros / 100.00;
      if ($tipo == 'S') {
        return $valorTotal * (1 + $juros);
      } else {
        return $valorTotal * pow((1 + $juros), $parcela);
      }
    }
  }

  public function saveLog($txt)
  {
    $filename = "/var/www/html/app/code/Tuna/TunaGateway/newfile.txt";
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
