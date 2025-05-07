<?php

namespace Tuna\TunaGateway\Controller\Order;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

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

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        SessionManager $sessionManager,
        CurlFactory $curlFactory,
        Json $jsonHelper,
        ScopeConfig $scopeConfig,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction

    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_pageFactory = $pageFactory;
        $this->_session = $sessionManager;
        $this->curlFactory = $curlFactory;
        $this->jsonHelper = $jsonHelper;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        return parent::__construct($context);
    }

    public function execute()
    {
        $appkey = $this->getRequest()->getHeader('Authorization');
        $body = $this->jsonHelper->unserialize(file_get_contents('php://input'));
        $orderID = $body['partnerUniqueId'];
        $status = $body['statusId'];
        if ($orderID==null)
        {
            echo print_r("ERROR - partneruniqueid parameter not found.");
            echo print_r(file_get_contents('php://input'));
            exit;
        }
        if ($status==null)
        {
            echo print_r("ERROR - status parameter not found.");
            echo print_r(file_get_contents('php://input'));
            exit;
        }
        if ($appkey==null)
        {
            echo print_r("ERROR - authorization header not found.");
            echo print_r($this->getRequest()->getHeader('Authorization'));
            exit;
        }
        if ('Bearer '.$this->scopeConfig->getValue('payment/tuna_payment/credentials/appKey')!= $appkey )
        {
            echo print_r("ERROR - invalid header.");
            exit;
        }
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderID );
        if ($order==null || $order->getStatus()=='')
        {
            echo print_r("ERROR - order not found.");
            exit;
        }
        $payment = $order->getPayment();
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
                $status = ('tuna_Captured');
                break;
            case '3':
                $status = ('tuna_Refunded');
                break;
            case '4':
                $status = ('tuna_Denied');
                break;
            case '5':
                $status = ('tuna_Cancelled');
                break;
            case '6':
                $status = ('tuna_Expired');
                break;
            case '7':
                $status = ('tuna_Chargeback');
                break;
            case '8':
                $status = ('tuna_MoneyReceived');
                break;
            case '9':
                $status = ('tuna_PartialCancel');
                break;
            case 'A':
                $status = ('tuna_Error');
                break;
            case 'B':
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
                $status = 'tuna_DeniedAntiFraud';
                break;
          }
        if ($status != $order->getStatus() && $order->getStatus()!= 'complete')
        {
            $order->addStatusToHistory($status, null, true);
            $order->setStatus($status);
            $order->save();
        }
        echo print_r("OK");
        exit;

    }
    public function saveLog2($txt)
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
}
