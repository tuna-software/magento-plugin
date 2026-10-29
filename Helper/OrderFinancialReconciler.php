<?php

namespace Tuna\TunaGateway\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;

class OrderFinancialReconciler
{
    private $creditmemoFactory;
    private $creditmemoManagement;
    private $debugLogger;

    public function __construct(
        CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        TunaDebugLogger $debugLogger
    ) {
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->debugLogger = $debugLogger;
    }

    public function synchronizePaidTotals(Order $order, array $notification): void
    {
        $this->synchronizeBaseGrandTotal($order);
        $grandTotal = max(0.0, (float)$order->getGrandTotal());
        if ($grandTotal <= 0) {
            return;
        }

        $reportedAmount = $this->getNumericValue($notification, 'amount');
        if ($reportedAmount === null || $reportedAmount <= 0) {
            $reportedAmount = $grandTotal;
        }
        $reportedAmount = round(min($reportedAmount, $grandTotal), 2);

        $baseToOrderRate = $this->getBaseToOrderRate($order);
        $baseGrandTotal = max(0.0, (float)$order->getBaseGrandTotal());
        $reportedBaseAmount = round($reportedAmount / $baseToOrderRate, 2);
        if ($baseGrandTotal > 0) {
            $reportedBaseAmount = min($reportedBaseAmount, $baseGrandTotal);
        }

        $payment = $order->getPayment();
        $orderPaid = max((float)$order->getTotalPaid(), $reportedAmount);
        $baseOrderPaid = max((float)$order->getBaseTotalPaid(), $reportedBaseAmount);
        $paymentPaid = max((float)$payment->getAmountPaid(), $reportedAmount);
        $basePaymentPaid = max((float)$payment->getBaseAmountPaid(), $reportedBaseAmount);

        if ($orderPaid <= (float)$order->getTotalPaid()
            && $baseOrderPaid <= (float)$order->getBaseTotalPaid()
            && $paymentPaid <= (float)$payment->getAmountPaid()
            && $basePaymentPaid <= (float)$payment->getBaseAmountPaid()
        ) {
            return;
        }

        $order->setTotalPaid($orderPaid);
        $order->setBaseTotalPaid($baseOrderPaid);
        $payment->setAmountPaid($paymentPaid);
        $payment->setBaseAmountPaid($basePaymentPaid);
        $payment->save();
        $order->save();
    }

    public function createRefundCreditmemo(
        Order $order,
        array $notification,
        bool $fullRefund,
        string $refundType
    ): bool {
        $notificationId = $this->getValue($notification, 'operationId')
            ?? $this->getValue($notification, 'id');
        $tunaRefundedTotal = $this->getTunaRefundedTotal($notification);
        $transactionId = $notificationId === null || $notificationId === ''
            ? null
            : 'TUNA-WEBHOOK-' . hash('sha256', (string)$notificationId);
        if ($transactionId === null && !$fullRefund && $tunaRefundedTotal !== null) {
            $transactionId = 'TUNA-REFUND-' . hash(
                'sha256',
                $order->getIncrementId() . '|' . number_format($tunaRefundedTotal, 2, '.', '')
            );
        }

        if (!$fullRefund && $transactionId === null) {
            throw new LocalizedException(__('The Tuna partial-refund notification has no idempotency identifier.'));
        }

        if ($transactionId !== null) {
            $creditmemos = $order->getCreditmemosCollection();
            if ($creditmemos) {
                foreach ($creditmemos as $existingCreditmemo) {
                    if ((string)$existingCreditmemo->getTransactionId() === $transactionId) {
                        return true;
                    }
                }
            }
        }

        $remainingOrderAmount = max(
            0.0,
            round((float)$order->getTotalPaid() - (float)$order->getTotalRefunded(), 2)
        );
        $remainingBaseAmount = max(
            0.0,
            (float)$order->getBaseTotalPaid() - (float)$order->getBaseTotalRefunded()
        );
        if ($remainingOrderAmount <= 0.005 || $remainingBaseAmount <= 0.005) {
            return true;
        }

        if ($fullRefund) {
            $refundOrderAmount = $remainingOrderAmount;
            $refundBaseAmount = $remainingBaseAmount;
        } else {
            $refundAmount = $tunaRefundedTotal !== null
                ? round($tunaRefundedTotal - (float)$order->getTotalRefunded(), 2)
                : $this->getPartialRefundAmount($notification);
            if ($refundAmount === null || $refundAmount <= 0) {
                return $tunaRefundedTotal !== null;
            }

            $refundOrderAmount = min($remainingOrderAmount, round($refundAmount, 2));
            $refundBaseAmount = min(
                $remainingBaseAmount,
                round($refundOrderAmount / $this->getBaseToOrderRate($order), 2)
            );
        }

        if ($refundOrderAmount <= 0.005 || $refundBaseAmount <= 0.005) {
            return true;
        }

        $itemQuantities = [];
        foreach ($order->getAllItems() as $orderItem) {
            if ($orderItem->getId()) {
                $itemQuantities[$orderItem->getId()] = 0;
            }
        }

        $creditmemo = $this->creditmemoFactory->createByOrder($order, [
            'qtys' => $itemQuantities,
            'shipping_amount' => 0,
            'adjustment_positive' => $refundBaseAmount,
        ]);

        if (abs((float)$creditmemo->getGrandTotal() - $refundOrderAmount) > 0.01
            || abs((float)$creditmemo->getBaseGrandTotal() - $refundBaseAmount) > 0.01
        ) {
            throw new LocalizedException(__('The generated Magento credit memo does not match the Tuna refund amount.'));
        }

        $creditmemo->setPaymentRefundDisallowed(true);
        if ($transactionId !== null) {
            $creditmemo->setTransactionId($transactionId);
        }
        $comment = $refundType === 'chargeback'
            ? __('Chargeback confirmed by Tuna payment notification.')
            : ($fullRefund
                ? __('Refund confirmed by Tuna payment notification.')
                : __('Partial refund confirmed by Tuna payment notification.'));
        $creditmemo->addComment($comment, false, false);

        $this->creditmemoManagement->refund($creditmemo, true);
        $this->debugLogger->log('payment.creditmemo.created', [
            'orderId' => $order->getIncrementId(),
            'creditmemoId' => $creditmemo->getId(),
            'refundType' => $refundType,
            'refundAmount' => $creditmemo->getGrandTotal(),
            'baseRefundAmount' => $creditmemo->getBaseGrandTotal(),
        ]);

        return true;
    }

    public function synchronizeCancellationTotals(Order $order, array $notification): void
    {
        $this->synchronizeBaseGrandTotal($order);
        $previousTotalCanceled = (float)$order->getTotalCanceled();
        $previousBaseTotalCanceled = (float)$order->getBaseTotalCanceled();
        $previousPaymentCanceled = (float)$order->getPayment()->getAmountCanceled();
        $previousBasePaymentCanceled = (float)$order->getPayment()->getBaseAmountCanceled();
        $order->registerCancellation(__('Cancellation confirmed by Tuna webhook.'), true);

        $maximumTotalCanceled = max(
            0.0,
            round((float)$order->getGrandTotal() - (float)$order->getTotalPaid(), 2)
        );
        $maximumBaseTotalCanceled = max(
            0.0,
            round((float)$order->getBaseGrandTotal() - (float)$order->getBaseTotalPaid(), 2)
        );
        $currentTotalCanceled = max($previousTotalCanceled, (float)$order->getTotalCanceled());
        $currentBaseTotalCanceled = max($previousBaseTotalCanceled, (float)$order->getBaseTotalCanceled());
        $targetTotalCanceled = max($previousTotalCanceled, min($currentTotalCanceled, $maximumTotalCanceled));
        $targetBaseTotalCanceled = max(
            $previousBaseTotalCanceled,
            min($currentBaseTotalCanceled, $maximumBaseTotalCanceled)
        );
        $canceledAmount = max(0.0, $targetTotalCanceled - $previousTotalCanceled);
        $baseCanceledAmount = max(0.0, $targetBaseTotalCanceled - $previousBaseTotalCanceled);
        $remainingOrderAmount = max(0.0, $maximumTotalCanceled - $previousTotalCanceled);
        $remainingBaseAmount = max(0.0, $maximumBaseTotalCanceled - $previousBaseTotalCanceled);

        if ($canceledAmount <= 0.005 && $remainingOrderAmount > 0.005) {
            $reportedAmount = $this->getNumericValue($notification, 'amount');
            $canceledAmount = $reportedAmount !== null && $reportedAmount > 0
                ? min($reportedAmount, $remainingOrderAmount)
                : $remainingOrderAmount;
            $baseCanceledAmount = min(
                $remainingBaseAmount,
                round($canceledAmount / $this->getBaseToOrderRate($order), 2)
            );
        }

        $targetTotalCanceled = min($maximumTotalCanceled, $previousTotalCanceled + $canceledAmount);
        $targetBaseTotalCanceled = min($maximumBaseTotalCanceled, $previousBaseTotalCanceled + $baseCanceledAmount);
        $order->setTotalCanceled($targetTotalCanceled);
        $order->setBaseTotalCanceled($targetBaseTotalCanceled);
        $payment = $order->getPayment();
        $payment->setAmountCanceled(max($previousPaymentCanceled, $targetTotalCanceled));
        $payment->setBaseAmountCanceled(max($previousBasePaymentCanceled, $targetBaseTotalCanceled));
        $payment->save();
    }

    public function hasPartialRefundAmount(array $notification): bool
    {
        $methods = $this->getValue($notification, 'methods');
        if (is_array($methods)) {
            foreach ($methods as $method) {
                if (!is_array($method)) {
                    continue;
                }
                $methodStatus = (string)($this->getValue($method, 'status') ?? '');
                if ($methodStatus !== '' && !in_array($methodStatus, ['3', '9'], true)) {
                    continue;
                }
                $operationAmount = $this->getNumericValue($method, 'operationAmount');
                if ($operationAmount !== null && $operationAmount > 0) {
                    return true;
                }
            }
        }

        foreach (['operationAmount', 'refundAmount', 'refundedAmount', 'amountRefunded'] as $key) {
            $amount = $this->getNumericValue($notification, $key);
            if ($amount !== null && $amount > 0) {
                return true;
            }
        }

        return false;
    }

    public function hasPartialRefundMetadata(array $notification): bool
    {
        if (($this->getTunaRefundedTotal($notification) ?? 0.0) > 0) {
            return true;
        }

        $notificationId = $this->getValue($notification, 'operationId')
            ?? $this->getValue($notification, 'id');

        return $notificationId !== null
            && $notificationId !== ''
            && $this->hasPartialRefundAmount($notification);
    }

    private function getTunaRefundedTotal(array $notification): ?float
    {
        $methods = $this->getValue($notification, 'methods');
        if (!is_array($methods)) {
            return null;
        }

        $found = false;
        $total = 0.0;
        foreach ($methods as $method) {
            $refunds = is_array($method) ? $this->getValue($method, 'refunds') : null;
            if (!is_array($refunds)) {
                continue;
            }
            foreach ($refunds as $refund) {
                $amount = is_array($refund) ? $this->getNumericValue($refund, 'amount') : null;
                if ($amount !== null && $amount > 0) {
                    $found = true;
                    $total += $amount;
                }
            }
        }

        return $found ? round($total, 2) : null;
    }

    private function getPartialRefundAmount(array $notification): ?float
    {
        $methods = $this->getValue($notification, 'methods');
        if (is_array($methods)) {
            $total = 0.0;
            foreach ($methods as $method) {
                if (!is_array($method)) {
                    continue;
                }
                $methodStatus = (string)($this->getValue($method, 'status') ?? '');
                if ($methodStatus !== '' && !in_array($methodStatus, ['3', '9'], true)) {
                    continue;
                }
                $operationAmount = $this->getNumericValue($method, 'operationAmount');
                if ($operationAmount !== null && $operationAmount > 0) {
                    $total += $operationAmount;
                }
            }
            if ($total > 0) {
                return round($total, 2);
            }
        }

        foreach (['operationAmount', 'refundAmount', 'refundedAmount', 'amountRefunded'] as $key) {
            $amount = $this->getNumericValue($notification, $key);
            if ($amount !== null && $amount > 0) {
                return round($amount, 2);
            }
        }

        return null;
    }

    private function getBaseToOrderRate(Order $order): float
    {
        $rate = (float)$order->getBaseToOrderRate();
        return $rate > 0 ? $rate : 1.0;
    }

    private function synchronizeBaseGrandTotal(Order $order): void
    {
        $grandTotal = max(0.0, (float)$order->getGrandTotal());
        $baseGrandTotal = round($grandTotal / $this->getBaseToOrderRate($order), 2);
        if (abs((float)$order->getBaseGrandTotal() - $baseGrandTotal) > 0.01) {
            $order->setBaseGrandTotal($baseGrandTotal);
            $order->save();
        }
    }

    private function getNumericValue(array $data, string $key): ?float
    {
        $value = $this->getValue($data, $key);
        return is_numeric($value) ? (float)$value : null;
    }

    private function getValue(array $data, string $key)
    {
        foreach ($data as $candidateKey => $value) {
            if (is_string($candidateKey) && strcasecmp($candidateKey, $key) === 0) {
                return $value;
            }
        }
        return null;
    }
}