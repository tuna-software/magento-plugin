<?php declare(strict_types=1);


namespace Tuna\TunaGateway\Model\Payment;


class Tuna extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = "tuna";

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }
        return $this;
    }
}

