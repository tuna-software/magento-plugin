<?php

namespace Tuna\TunaGateway\Controller\Order;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Tuna\TunaGateway\Helper\OrderFinancialReconciler;
use Tuna\TunaGateway\Helper\OrderStatusResolver;
use Tuna\TunaGateway\Helper\TunaDebugLogger;

class Update extends Action
{
    protected PageFactory$_pageFactory;
    protected $_session;
    protected $curl;
    protected $invoiceService;
    protected $transaction;
    protected $invoiceSender;
    protected ScopeConfig $scopeConfig;
    protected CurlFactory $curlFactory;
    protected Json $jsonHelper;
    protected OrderStatusResolver $orderStatusResolver;
    protected TunaDebugLogger $tunaDebugLogger;
    protected OrderFinancialReconciler $orderFinancialReconciler;
    protected Curl $curlClient;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        SessionManager $sessionManager,
        CurlFactory $curlFactory,
        Json $jsonHelper,
        ScopeConfig $scopeConfig,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        OrderStatusResolver $orderStatusResolver,
        TunaDebugLogger $tunaDebugLogger,
        OrderFinancialReconciler $orderFinancialReconciler,
        Curl $curlClient
    ) {
        $this->curlClient = $curlClient;
        $this->scopeConfig = $scopeConfig;
        $this->_pageFactory = $pageFactory;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->orderStatusResolver = $orderStatusResolver;
        $this->tunaDebugLogger = $tunaDebugLogger;
        $this->orderFinancialReconciler = $orderFinancialReconciler;
        return parent::__construct($context);
    }

    public function execute()
    {
        $appkey = $this->getRequest()->getHeader('Authorization');
        $rawBody = (string)file_get_contents('php://input');
        $this->tunaDebugLogger->log('webhook.request', [
            'method' => $this->getRequest()->getMethod(),
            'headers' => ['Authorization' => $appkey],
            'body' => $rawBody,
        ]);

        try {
        try {
            $body = $this->jsonHelper->unserialize($rawBody);
        } catch (\InvalidArgumentException $exception) {
            $this->respond('ERROR - invalid JSON.');
        }
        if (!is_array($body)) {
            $this->respond('ERROR - invalid payload.');
        }

        $orderID = $body['partnerUniqueId'] ?? null;
        $status = $body['statusId'] ?? null;
        if ($orderID==null)
        {
            $this->respond('ERROR - partneruniqueid parameter not found.');
        }
        if ($status==null)
        {
            $this->respond('ERROR - status parameter not found.');
        }
        if ($appkey==null)
        {
            $this->respond('ERROR - authorization header not found.');
        }
        if ('Bearer '.$this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey')!= $appkey )
        {
            $this->respond('ERROR - invalid header.');
        }
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderID );
        if (!$order || !$order->getId() || $order->getStatus()=='')
        {
            $this->respond('ERROR - order not found.');
        }
        $callbackStatusId = (string)$status;
        $verified = $this->fetchTunaStatus($order, (string)$orderID);
        if ($verified === null) {
            $this->respond(
                'ERROR - could not validate status on Tuna.',
                ['orderId' => $orderID, 'statusId' => $callbackStatusId],
                503
            );
        }
        $status = $verified['status'];
        $body = $verified['response'] + $body;
        $this->tunaDebugLogger->log('webhook.status.verified', [
            'orderId' => $orderID,
            'callbackStatusId' => $callbackStatusId,
            'verifiedStatusId' => $status,
        ]);
        $rawStatusId = $status;
        switch ($status.'') {
            case '0':
                $status = ('tuna_Started');
                break;
            case '1':
                $status = ('tuna_Authorized');
                break;
            case '2':
                if ( $this->scopeConfig->getValue('payment/tuna_payment/options/auto_invoice')=="1"){
                    if ($order->canInvoice()) {
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->register();
                        $invoice->save();
                        try{
                            $transactionSave =
                                $this->transaction
                                    ->addObject($invoice)
                                    ->addObject($invoice->getOrder());
                            $transactionSave->save();

                            $this->invoiceSender->send($invoice);
                            $order->addCommentToStatusHistory(
                                __('Usuário notificado sobre o pedido #%1.', $invoice->getId())
                            )->setIsCustomerNotified(true)->save();
                        } catch (\Exception $e) {}
                    }
                }
                $this->orderFinancialReconciler->synchronizePaidTotals($order, $body);
                $status = ('tuna_Captured');
                break;
            case '3':
                $this->orderFinancialReconciler->synchronizePaidTotals($order, $body);
                $this->orderFinancialReconciler->createRefundCreditmemo($order, $body, true, 'refund');
                $status = ('tuna_Refunded');
                break;
            case '4':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = ('tuna_Denied');
                break;
            case '5':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = ('tuna_Cancelled');
                break;
            case '6':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = ('tuna_Expired');
                break;
            case '7':
                $this->orderFinancialReconciler->synchronizePaidTotals($order, $body);
                $this->orderFinancialReconciler->createRefundCreditmemo($order, $body, true, 'chargeback');
                $status = ('tuna_Chargeback');
                break;
            case '8':
                $this->orderFinancialReconciler->synchronizePaidTotals($order, $body);
                $status = ('tuna_MoneyReceived');
                break;
            case '9':
                if (!$this->orderFinancialReconciler->hasPartialRefundMetadata($body)) {
                    $this->tunaDebugLogger->log('webhook.partial_refund.waiting_for_amount', [
                        'orderId' => $orderID,
                        'notificationId' => $body['operationId'] ?? $body['id'] ?? null,
                    ]);
                    $this->respond(
                        'ERROR - partial refund amount or operation identifier missing.',
                        ['orderId' => $orderID, 'statusId' => $rawStatusId],
                        503
                    );
                }
                $this->orderFinancialReconciler->synchronizePaidTotals($order, $body);
                $this->orderFinancialReconciler->createRefundCreditmemo($order, $body, false, 'partial_refund');
                $status = ('tuna_PartialCancel');
                break;
            case '-1':
            case 'N':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = ('tuna_Cancelled');
                break;
            case 'A':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = ('tuna_Error');
                break;
            case 'B':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = ('tuna_RedFlag');
                break;
            case 'C':
            case 'P':
                $status = ('tuna_PendingCapture');
                break;
            case 'D':
                $status = ('tuna_PendingAntiFraud');
                break;
            case 'E':
                $this->orderFinancialReconciler->synchronizeCancellationTotals($order, $body);
                $status = 'tuna_DeniedAntiFraud';
                break;
          }
        $canonicalStatus = $status;
        $resolved = $this->orderStatusResolver->resolve($canonicalStatus);

        // registra o callback recebido independentemente de haver troca de status
        $callbackComment = __(
            'Callback Tuna recebido. StatusId do callback: %1. StatusId confirmado na Tuna: %2.',
            $callbackStatusId,
            $rawStatusId
        );
        $order->addCommentToStatusHistory($callbackComment, false)->save();

        $finalFinancialStatus = in_array((string)$rawStatusId, ['3', '5', '7', '9'], true);
        if ($resolved['status'] != $order->getStatus()
            && ($order->getState() != Order::STATE_COMPLETE || $finalFinancialStatus)
        )
        {
            $order->addStatusToHistory($resolved['status'], null, true);
            $order->setState($resolved['state']);
            $order->setStatus($resolved['status']);
            $order->save();
        }
        $this->respond('OK', [
            'orderId' => $orderID,
            'statusId' => $rawStatusId,
            'resolvedStatus' => $resolved['status'],
        ]);
        } catch (\Throwable $exception) {
            $this->tunaDebugLogger->log('webhook.error', [
                'orderId' => $orderID ?? null,
                'statusId' => $rawStatusId ?? $status ?? null,
                'exception' => get_class($exception),
            ]);
            throw $exception;
        }

    }

    private function fetchTunaStatus(Order $order, string $orderID): ?array
    {
        $url = $this->scopeConfig->getValue('payment/tuna_payment/options/endpoint_config') == 'production'
            ? 'https://engine.tunagateway.com/api/Payment/Status'
            : 'https://sandbox.tuna-demo.uy/api/Payment/Status';
        $appKey = (string)$this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey');
        $partnerAccount = (string)$this->scopeConfig->getValue('payment/tuna_payment/credentials/partner_account');
        $requestBody = $this->jsonHelper->serialize([
            'PartnerUniqueID' => $orderID,
            'PaymentDate' => date('Y-m-d', strtotime($order->getCreatedAt()) + 19800),
        ]);

        $this->tunaDebugLogger->log('webhook.status.request', [
            'orderId' => $orderID,
            'endpoint' => $url,
            'body' => $requestBody,
        ]);
        try {
            $this->curlClient->addHeader('Content-Type', 'application/json');
            $this->curlClient->addHeader('x-tuna-apptoken', $appKey);
            $this->curlClient->addHeader('x-tuna-account', $partnerAccount);
            $this->curlClient->post($url, $requestBody);
            $responseBody = $this->curlClient->getBody();
            $response = $this->jsonHelper->unserialize($responseBody);
        } catch (\Throwable $exception) {
            $this->tunaDebugLogger->log('webhook.status.error', [
                'orderId' => $orderID,
                'exception' => get_class($exception),
            ]);
            return null;
        }
        $this->tunaDebugLogger->log('webhook.status.response', [
            'orderId' => $orderID,
            'httpStatus' => $this->curlClient->getStatus(),
            'body' => $responseBody,
        ]);
        if (!is_array($response)) {
            return null;
        }
        $response = $this->normalizeKeys($response);
        $status = $response['status'] ?? null;
        if (($response['code'] ?? null) == '-1' && $status === null) {
            return null;
        }
        if ($status === null || $status === '') {
            return null;
        }

        return ['status' => (string)$status, 'response' => $response];
    }

    private function normalizeKeys(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[is_string($key) ? strtolower($key) : $key] = is_array($value)
                ? $this->normalizeKeys($value)
                : $value;
        }
        return $normalized;
    }

    private function respond(string $response, array $context = [], int $httpStatus = 200): void
    {
        $this->tunaDebugLogger->log('webhook.response', array_merge($context, ['body' => $response]));
        $this->getResponse()->setHttpResponseCode($httpStatus);
        echo $response;
        exit;
    }
}
