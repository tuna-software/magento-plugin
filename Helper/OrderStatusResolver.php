<?php

namespace Tuna\TunaGateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;

class OrderStatusResolver extends AbstractHelper
{
    const CONFIG_PATH_USE_CUSTOM_STATUS = 'payment/tuna_payment/options/use_custom_status';

    /**
     * Mapa De->Para: status Tuna => state interno do Magento e status nativo equivalente
     */
    const STATUS_MAP = [
        'tuna_Started'          => ['state' => Order::STATE_NEW,            'native' => 'pending'],
        'tuna_Authorized'       => ['state' => Order::STATE_PROCESSING,     'native' => 'processing'],
        'tuna_PendingCapture'   => ['state' => Order::STATE_PROCESSING,     'native' => 'processing'],
        'tuna_PendingAntiFraud' => ['state' => Order::STATE_PAYMENT_REVIEW, 'native' => 'payment_review'],
        'tuna_Captured'         => ['state' => Order::STATE_COMPLETE,       'native' => 'complete'],
        'tuna_MoneyReceived'    => ['state' => Order::STATE_COMPLETE,       'native' => 'complete'],
        'tuna_PartialCancel'    => ['state' => Order::STATE_COMPLETE,       'native' => 'complete'],
        'tuna_Refunded'         => ['state' => Order::STATE_CLOSED,         'native' => 'closed'],
        'tuna_DeniedAntiFraud'  => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
        'tuna_Expired'          => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
        'tuna_RedFlag'          => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
        'tuna_Chargeback'       => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
        'tuna_Denied'           => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
        'tuna_Cancelled'        => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
        'tuna_Error'            => ['state' => Order::STATE_CANCELED,       'native' => 'canceled'],
    ];

    protected $scopeConfig;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Ausência de configuração mantém o comportamento legado (status customizado)
     */
    public function isCustomStatusEnabled(): bool
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_USE_CUSTOM_STATUS);
        return $value === null ? true : (bool)$value;
    }

    /**
     * Resolve o status/state a aplicar no pedido para um código Tuna, respeitando a flag admin
     */
    public function resolve(string $tunaStatusCode): array
    {
        if (!isset(self::STATUS_MAP[$tunaStatusCode])) {
            return ['status' => $tunaStatusCode, 'state' => $tunaStatusCode];
        }

        $map = self::STATUS_MAP[$tunaStatusCode];
        return [
            'status' => $this->isCustomStatusEnabled() ? $tunaStatusCode : $map['native'],
            'state' => $map['state'],
        ];
    }

    /**
     * Aplica o status/state resolvidos no pedido (sem salvar)
     */
    public function apply(Order $order, string $tunaStatusCode, bool $addToHistory = false, bool $notify = false): void
    {
        $resolved = $this->resolve($tunaStatusCode);
        $order->setState($resolved['state']);
        $order->setStatus($resolved['status']);
        if ($addToHistory) {
            $order->addStatusToHistory($resolved['status'], null, $notify);
        }
    }

    /**
     * Compara o status atual do pedido com o status resolvido para um código Tuna
     */
    public function matches(string $currentOrderStatus, string $tunaStatusCode): bool
    {
        return $currentOrderStatus === $this->resolve($tunaStatusCode)['status'];
    }

    /**
     * Compara o status atual do pedido com o status resolvido para uma lista de códigos Tuna
     */
    public function matchesAny(string $currentOrderStatus, array $tunaStatusCodes): bool
    {
        foreach ($tunaStatusCodes as $tunaStatusCode) {
            if ($this->matches($currentOrderStatus, $tunaStatusCode)) {
                return true;
            }
        }
        return false;
    }
}
