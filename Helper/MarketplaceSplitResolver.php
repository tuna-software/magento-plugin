<?php

namespace Tuna\TunaGateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Item;

class MarketplaceSplitResolver extends AbstractHelper
{
    protected $scopeConfig;
    protected $objectManager;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        ObjectManagerInterface $objectManager
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->objectManager = $objectManager;
    }

    /**
     * Verifica se o split está ativo
     */
    public function isSplitEnabled()
    {
        return $this->scopeConfig->getValue('payment/tuna_payment/options/split_enabled') == '1';
    }

    /**
     * Método principal para obter split de um item
     * Retorna array com split ou null se não encontrar/não aplicável
     */
    public function getSplitForItem(Item $orderItem)
    {
        if (!$this->isSplitEnabled()) {
            return null;
        }

        $sellerId = $this->detectSellerFromItem($orderItem);
        
        if ($sellerId ) {
            return [
                'merchantId' => $sellerId
            ];
        }

        return null;
    }

    /**
     * Detecta seller_id do item baseado nos módulos marketplace disponíveis
     */
    protected function detectSellerFromItem(Item $orderItem)
    {
        // Estratégia 1: Tentar Webkul Marketplace
        $sellerId = $this->getWebkulSellerId($orderItem);
        if ($sellerId) {
            return $sellerId;
        }

        // Estratégia 2: Tentar CedCommerce Marketplace
        $sellerId = $this->getCedCommerceSellerId($orderItem);
        if ($sellerId) {
            return $sellerId;
        }

        // Estratégia 3: Tentar Magenest Marketplace
        $sellerId = $this->getMagenestSellerId($orderItem);
        if ($sellerId) {
            return $sellerId;
        }

        // Estratégia 4: Tentar Landofcoder Marketplace
        $sellerId = $this->getLandofcoderSellerId($orderItem);
        if ($sellerId) {
            return $sellerId;
        }

        // Estratégia 5: Verificar custom attributes genéricos
        $sellerId = $this->getGenericSellerId($orderItem);
        if ($sellerId) {
            return $sellerId;
        }

        return null;
    }

    /**
     * Webkul Marketplace Multi Vendor Module
     * Busca na tabela marketplace_saleslist
     */
    protected function getWebkulSellerId(Item $orderItem)
    {
        try {
            // Verifica se o módulo Webkul está instalado
            if (!class_exists('\Webkul\Marketplace\Model\SaleslistFactory')) {
                return null;
            }

            $saleslistFactory = $this->objectManager->get('\Webkul\Marketplace\Model\SaleslistFactory');
            $saleslistCollection = $saleslistFactory->create()
                ->getCollection()
                ->addFieldToFilter('order_item_id', $orderItem->getId());
                
            $salesItem = $saleslistCollection->getFirstItem();
            if ($salesItem && $salesItem->getSellerId()) {
                return $salesItem->getSellerId();
            }
        } catch (\Exception $e) {
            $this->_logger->debug('Webkul marketplace detection error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * CedCommerce Marketplace Extension  
     * Verifica additional_information e tabelas customizadas
     */
    protected function getCedCommerceSellerId(Item $orderItem)
    {
        try {
            // Método 1: Verificar additional_information
            $additionalInfo = $orderItem->getAdditionalInformation();
            if (is_array($additionalInfo) && isset($additionalInfo['vendor_id'])) {
                return $additionalInfo['vendor_id'];
            }

            // Método 2: Verificar custom attribute
            if (method_exists($orderItem, 'getVendorId') && $orderItem->getVendorId()) {
                return $orderItem->getVendorId();
            }

            // Método 3: Tentar buscar na tabela ced_vendor_sales_order
            if (class_exists('\Ced\CsMarketplace\Model\VendorSalesOrderFactory')) {
                $vendorSalesOrderFactory = $this->objectManager->get('\Ced\CsMarketplace\Model\VendorSalesOrderFactory');
                $vendorSalesOrder = $vendorSalesOrderFactory->create()
                    ->load($orderItem->getId(), 'order_item_id');
                    
                if ($vendorSalesOrder && $vendorSalesOrder->getVendorId()) {
                    return $vendorSalesOrder->getVendorId();
                }
            }
        } catch (\Exception $e) {
            $this->_logger->debug('CedCommerce marketplace detection error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Magenest Marketplace
     * Busca na tabela magenest_seller_order
     */
    protected function getMagenestSellerId(Item $orderItem)
    {
        try {
            if (!class_exists('\Magenest\Seller\Model\SellerOrderFactory')) {
                return null;
            }

            $sellerOrderFactory = $this->objectManager->get('\Magenest\Seller\Model\SellerOrderFactory');
            $sellerOrder = $sellerOrderFactory->create()
                ->load($orderItem->getId(), 'order_item_id');
                
            if ($sellerOrder && $sellerOrder->getSellerId()) {
                return $sellerOrder->getSellerId();
            }
        } catch (\Exception $e) {
            $this->_logger->debug('Magenest marketplace detection error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Landofcoder Magento 2 Marketplace
     * Busca na tabela lof_marketplace_seller_order  
     */
    protected function getLandofcoderSellerId(Item $orderItem)
    {
        try {
            if (!class_exists('\Lof\MarketPlace\Model\SellerOrderFactory')) {
                return null;
            }

            $sellerOrderFactory = $this->objectManager->get('\Lof\MarketPlace\Model\SellerOrderFactory');
            $sellerOrder = $sellerOrderFactory->create()
                ->getCollection()
                ->addFieldToFilter('order_item_id', $orderItem->getId())
                ->getFirstItem();
                
            if ($sellerOrder && $sellerOrder->getSellerId()) {
                return $sellerOrder->getSellerId();
            }
        } catch (\Exception $e) {
            $this->_logger->debug('Landofcoder marketplace detection error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Verificação genérica para outros módulos marketplace
     * Busca por padrões comuns de nomenclatura
     */
    protected function getGenericSellerId(Item $orderItem)
    {
        try {
            // Tentar métodos getter comuns
            $commonGetters = [
                'getSellerId',
                'getVendorId', 
                'getMarketplaceSellerId',
                'getMarketplaceVendorId'
            ];

            foreach ($commonGetters as $getter) {
                if (method_exists($orderItem, $getter)) {
                    $sellerId = $orderItem->$getter();
                    if ($sellerId) {
                        return $sellerId;
                    }
                }
            }

            // Verificar additional_information por campos comuns
            $additionalInfo = $orderItem->getAdditionalInformation();
            if (is_array($additionalInfo)) {
                $commonFields = [
                    'seller_id',
                    'vendor_id',
                    'marketplace_seller_id',
                    'marketplace_vendor_id'
                ];

                foreach ($commonFields as $field) {
                    if (isset($additionalInfo[$field]) && $additionalInfo[$field]) {
                        return $additionalInfo[$field];
                    }
                }
            }

            // Verificar custom attributes do produto relacionado
            $product = $orderItem->getProduct();
            if ($product) {
                foreach ($commonGetters as $getter) {
                    if (method_exists($product, $getter)) {
                        $sellerId = $product->$getter();
                        if ($sellerId) {
                            return $sellerId;
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->_logger->debug('Generic marketplace detection error: ' . $e->getMessage());
        }

        return null;
    }
}