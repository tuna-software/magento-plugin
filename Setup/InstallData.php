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
            'tuna_Started'          => __('Iniciada'),
            'tuna_Authorized'       => __('Sucesso'),
            'tuna_Captured'         => __('Finalizada com sucesso'),
            'tuna_Refunded'         => __('Reembolsada'),
            'tuna_Denied'           => __('Negada'),
            'tuna_Cancelled'        => __('Cancelada'),
            'tuna_Expired'          => __('Expirada'),
            'tuna_Chargeback'       => __('Chargeback'),
            'tuna_MoneyReceived'    => __('Finalizada com sucesso*'),
            'tuna_PartialCancel'    => __('Parcialmente cancelada'),
            'tuna_Error'            => __('Erro'),
            'tuna_RedFlag'          => __('Erro*'),
            'tuna_PendingCapture'   => __('Pendente'),
            'tuna_PendingAntiFraud' => __('Em análise'),
            'tuna_DeniedAntiFraud'  => __('Negada*')
        ];

        $status = [];
        $state = [];

        foreach ($statuses as $code => $info) {
            $status[] = [
                'status' => $code,
                'label'  => $info
            ];
            
            // Define 'new' como padrão
            $tmpState = 'new';
            
            // Concluído
            if (in_array($code, ['tuna_Captured', 'tuna_MoneyReceived', 'tuna_PartialCancel'])) {
                $tmpState = 'complete';
            }
            
            // Processando
            elseif (in_array($code, ['tuna_Authorized', 'tuna_PendingCapture'])) {
                $tmpState = 'processing';
            }
            
            // Revisão de Pagamento (Antifraude)
            elseif ($code == 'tuna_PendingAntiFraud') {
                $tmpState = 'payment_review';
            }
            
            // Fechado (Reembolsado)
            elseif ($code == 'tuna_Refunded') {
                $tmpState = 'closed';
            }
            
            // Cancelado (Engloba as novas regras que você passou)
            elseif (in_array($code, [
                'tuna_DeniedAntiFraud', 
                'tuna_Expired', 
                'tuna_RedFlag', 
                'tuna_Chargeback', 
                'tuna_Denied', 
                'tuna_Cancelled', 
                'tuna_Error'
            ])) {
                $tmpState = 'canceled';
            }

            $state[] = [
                'status'           => $code,
                'state'            => $tmpState,
                'is_default'       => 0,
                'visible_on_front' => '1'
            ];
        }

        $setup->getConnection()
            ->insertArray($setup->getTable('sales_order_status'), ['status', 'label'], $status);

        // Define o tuna_Started como default para o state 'new', se for essa a intenção original
        // Obs: Cuidado ao setar is_default = 1, pois pode sobrescrever o default nativo do Magento. 
        // Se a intenção é só criar os status, talvez seja melhor deixar tudo como 0. 
        // Mas mantive a sua lógica original abaixo.
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
