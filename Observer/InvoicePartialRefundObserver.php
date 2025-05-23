<?php

namespace Tuna\TunaGateway\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterface as Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Observer\AbstractDataAssignObserver;

class InvoicePartialRefundObserver extends AbstractDataAssignObserver
{
    /**
     * @param Observer $observer
     * @throws LocalizedException
     */

    protected $_scopeConfig;
    protected $_tunaEndpointDomain;
    protected $curlClient;
    protected $jsonHelper;


    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        Curl $curl,
        Json $jsonHelper
    ) {
        $this->_scopeConfig = $scopeConfigInterface;
        $this->curlClient = $curl;
        $this->jsonHelper = $jsonHelper;

        if ($this->_scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') == 'production') {
            $this->_tunaEndpointDomain = 'engine.tunagateway.com';
        } else {
            $this->_tunaEndpointDomain = 'sandbox.tuna-demo.uy';
        }
    }

    public function execute(Observer $observer)
    {
        if ($this->_scopeConfig->getValue('payment/tuna_payment/options/enable_partial_refund_with_invoice') == "1") {
            $invoice = $observer->getEvent()->getInvoice();
            $order = $invoice->getOrder();

            if($order == null || $invoice == null)
                return;

            /// test if the partial refund was already done
            $refundAllowed = true;
            foreach ($order->getStatusHistoryCollection() as $status) {
                if (strpos($status->getComment(), 'Realizado estorno parcial') !== false) {
                    $refundAllowed = false;
                    break;
                }
            }

            $statusToRefund = $this->_scopeConfig->getValue('payment/tuna_payment/options/enable_partial_refund_with_invoice_order_status');
            if (
                $statusToRefund == $order->getStatus() &&
                $order->getPayment()->getMethod() == 'pix' &&
                $refundAllowed
            ) {
                $payment = $order->getPayment();
                $orderTotalPaid =  $this->roundDown($payment->getAdditionalInformation()["initial_total_value"],2);
                $invoiceGrandTotal = $this->roundDown($invoice->getGrandTotal(),2);

                $totalToRefund = $this->roundDown($orderTotalPaid - $invoiceGrandTotal,2);
                if ($totalToRefund > 0) {
                    $url  = 'https://' . $this->_tunaEndpointDomain . '/api/Payment/Cancel';

                    $requestbody = [
                        'AppToken' => $this->_scopeConfig->getValue('payment/tuna_payment/credentials/appKey'),
                        'Account' => $this->_scopeConfig->getValue('payment/tuna_payment/credentials/partner_account'),
                        'PartnerUniqueID' => $order->getIncrementId(),
                        'Origin' => 'TUNA-MAGENTO',
                        'PaymentDate' =>  date("Y-m-d", strtotime($order->getCreatedAt())),
                        'CardsDetail' => [
                            [
                                'amount' => $totalToRefund,
                                'methodId' => 0
                            ]
                        ]
                    ];
                    try {
                        $bodyJsonRequest = json_encode($requestbody);

                        $this->curlClient->addHeader("Content-Type", "application/json");
                        $this->curlClient->post($url, $bodyJsonRequest);
                        $body = $this->curlClient->getBody();

                        $response = $this->jsonHelper->unserialize($body);

                        if (strval($response["status"]) == '9' || strval($response["status"]) == '5') {
                            $order->addStatusHistoryComment('Realizado estorno parcial de R$' . number_format($totalToRefund, 2, ",", ".") . " após geração de invoice com valor menor que o da order");
                        } else {
                            $order->addStatusHistoryComment('Erro ao tentar realizar cancelamento parcial no valor de R$' . number_format($totalToRefund, 2, ",", ".") . ". Por favor proceder com reembolso parcial manual");
                        }
                    } catch (\Exception $e) {
                        $order->addStatusHistoryComment('Erro ao tentar realizar cancelamento parcial no valor de R$' . number_format($totalToRefund, 2, ",", ".") . ". Por favor proceder com reembolso parcial manual");
                    }
                }
            }
        }
    }

    private function saveLog($txt)
    {
        $filename = "/var/www/html/app/code/Tuna/TunaGateway/invoiceObserverLog.txt";
        $file = fopen($filename, "a");

        if ($file == false) {
            echo ("Error in opening new file");
            exit();
        }
        fwrite($file, $txt . " | " . date("j/n/Y h:i:s") . "\n");
        fclose($file);
    }

    function roundDown($decimal, $precision)
    {
        $sign = $decimal > 0 ? 1 : -1;
        $base = pow(10, $precision);
        return floor(abs($decimal) * $base) / $base * $sign;
    }
}
