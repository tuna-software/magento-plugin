<?php

namespace Tuna\TunaGateway\Controller\Payment;

class Request extends \Magento\Framework\App\Action\Action
{
    private $_checkoutSession;
    protected $resultJsonFactory;
    protected $result;
    private $orderId;

    public function __construct(\Magento\Framework\App\Action\Context $context)
    {
        parent::__construct($context);

        $this->resultJsonFactory = $this->_objectManager->create('\Magento\Framework\Controller\Result\JsonFactory');
        $this->result = $this->resultJsonFactory->create();
        $this->_checkoutSession = $this->_objectManager->create('\Magento\Checkout\Model\Session');
    }

    public function execute()
    {
        $lastRealOrder = $this->_checkoutSession->getLastRealOrder();

        if (is_null($lastRealOrder->getPayment())) {
            throw new \Magento\Framework\Exception\NotFoundException(__('No order associated.'));
        }
        $paymentData = $lastRealOrder->getPayment()->getData();

        if ($paymentData['method'] === 'tuna') {
            $this->orderId = $lastRealOrder->getId();
            $orderStatus = $lastRealOrder->getStatus();

            if (
                $orderStatus == "tuna_Started" ||
                $orderStatus == "tuna_Authorized" ||
                $orderStatus == "tuna_Captured"
            ) {
                $itemsCollection = $lastRealOrder->getAllVisibleItems();
                $orderProducts = [];
                foreach ($itemsCollection as $item) {
                    $cItem = [[
                        "Amount" => $item->getPrice(),
                        "ProductDescription" => $item->getProduct()->getName(),
                        "ItemQuantity" => $item->getQtyToInvoice()
                    ]];
                    $orderProducts  = array_merge($orderProducts, $cItem);
                }


                $this->session()->setData([
                    'tuna_payment' => [
                        'payment_type'  => $paymentData['method'],
                        'order_id'      => $this->orderId,
                        'order_products' => $orderProducts,
                        'order_status' => $orderStatus
                    ]
                ]);

                return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/success'));
            } else {
                $this->session()->setData([
                    'tuna_payment' => [
                        'payment_type'  => $paymentData['method'],
                        'order_id'      => $this->orderId,
                        'order_status' => $orderStatus
                    ]
                ]);
                return $this->_redirect(sprintf('%s%s', $this->baseUrl(), 'tunagateway/response/error'));
            }
        }
    }

    private function baseUrl()
    {
        return $this->_objectManager->create('Magento\Store\Model\StoreManagerInterface')->getStore()->getBaseUrl();
    }

    private function session()
    {
        return $this->_objectManager->create('Magento\Framework\Session\SessionManager');
    }
}
