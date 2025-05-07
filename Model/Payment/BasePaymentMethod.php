<?php declare(strict_types=1);


namespace Tuna\TunaGateway\Model\Payment;


class BasePaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null) {
        return parent::isAvailable($quote);
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }
        return $this;
    }

    public function getStandardCheckoutPaymentUrl()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        return $cart->getQuote()->getStore()->getUrl("tunagateway/payment/request");
    }
}

