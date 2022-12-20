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
            'tuna_Started'  => __('Iniciada'),
            'tuna_Authorized'  => __('Sucesso'),
            'tuna_Captured'  => __('Finalizada com sucesso'),
            'tuna_Refunded'  => __('Reembolsada'),
            'tuna_Denied'  => __('Negada'),
            'tuna_Cancelled'  => __('Cancelada'),
            'tuna_Expired'  => __('Expirada'),
            'tuna_Chargeback'  => __('Chargeback'),
            'tuna_MoneyReceived'  => __('Finalizada com sucesso*'),
            'tuna_PartialCancel'  => __('Parcialmente cancelada'),
            'tuna_Error'  => __('Erro'),
            'tuna_RedFlag'  => __('Erro*'),
            'tuna_PendingCapture'  => __('Pendente'),
            'tuna_PendingAntiFraud'  => __('Em análise'),
            'tuna_DeniedAntiFraud'  => __('Negada*')
        ];

        foreach ($statuses as $code => $info) {
            $status[] = [
                'status' => $code,
                'label' => $info
            ];
            $tmpState = 'new';
            if ($code == 'tuna_Captured' || $code == 'tuna_MoneyReceived' || $code== 'tuna_PartialCancel'){
                $tmpState = 'complete';
            }
            if ($code == 'tuna_Authorized' || $code == 'tuna_PendingCapture' || $code == 'tuna_PendingAntiFraud'){
                $tmpState = 'processing';
            }
            if ($code == 'tuna_DeniedAntiFraud' 
             
             
            || $code == 'tuna_Expired' 
            || $code == 'tuna_Refunded'
             
            || $code == 'tuna_RedFlag'
            || $code == 'tuna_Chargeback'){
                $tmpState = 'canceled';
            }
            $state[] = [
                'status' => $code,
                'state' => $tmpState,
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
