<?php

namespace Tuna\TunaGateway\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallData implements InstallDataInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $statuses = [
            'tuna_Started'  => __('Tuna Started'),
            'tuna_Authorized'  => __('Tuna Authorized'),
            'tuna_Captured'  => __('Tuna Captured'),
            'tuna_Refunded'  => __('Tuna Refunded'),
            'tuna_Denied'  => __('Tuna Denied'),
            'tuna_Cancelled'  => __('Tuna Cancelled'),
            'tuna_Expired'  => __('Tuna Expired'),
            'tuna_Chargeback'  => __('Tuna Chargeback'),
            'tuna_MoneyReceived'  => __('Tuna MoneyReceived'),
            'tuna_PartialCancel'  => __('Tuna PartialCancel'),
            'tuna_Error'  => __('Tuna Error'),
            'tuna_RedFlag'  => __('Tuna RedFlag'),
            'tuna_PendingCapture'  => __('Tuna PendingCapture'),
            'tuna_PendingAntiFraud'  => __('Tuna PendingAntiFraud'),
            'tuna_DeniedAntiFraud'  => __('Tuna DeniedAntiFraud')
        ];

        foreach ($statuses as $code => $info) {
            $status[] = [
                'status' => $code,
                'label' => $info
            ];
            $state[] = [
                'status' => $code,
                'state' => 'new',
                'is_default' => 0,
                'visible_on_front' => '1'
            ];
        }
        $setup->getConnection()
            ->insertArray($setup->getTable('sales_order_status'), ['status', 'label'], $status);

        $state[0]['is_default'] = 1;
        $setup->getConnection()
            ->insertArray(
                $setup->getTable('sales_order_status_state'),
                ['status', 'state', 'is_default', 'visible_on_front'],
                $state
            );

        $setup->endSetup();
    }
}
