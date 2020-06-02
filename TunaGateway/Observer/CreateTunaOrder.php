<?php

namespace Tuna\TunaGateway\Observer;
use Magento\Framework\Event\ObserverInterface;

class CreateTunaOrder implements ObserverInterface
{
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
        $order = $observer->getEvent()->getOrder();

        //verify pagseguro transaction
        if ($order->getStatus() == 'tuna_begin_payment') {
            $orderId = $order->getId();
            
            //save order in orders table
            // $this->saveOrderAndEnvironment($orderId, $environment);

            // //$this->getEnvironmentName($environment);
            // $this->updateSalesOrderGridEnvironment($orderId, $environment);
        }

        return $this;
	}
}