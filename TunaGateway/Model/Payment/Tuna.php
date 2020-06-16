<?php declare(strict_types=1);


namespace Tuna\TunaGateway\Model\Payment;


class Tuna extends \Magento\Payment\Model\Method\AbstractMethod
{

    // public function __construct(
    //     // \Magento\Framework\Model\Context $context,
    //     // \Magento\Framework\Registry $registry,
    //     // \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
    //     // \Magento\Framework\Api\AttributeValueFactory $attributeFactory,
    //     // \Magento\Payment\Helper\Data $paymentData,
    //     // \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    //     // \Magento\Payment\Model\Method\Logger $logger,
    //     \Magento\Checkout\Model\Cart $cart
    // ) {

    //     // parent::__construct(
    //     //     $context,
    //     //     $registry,
    //     //     $extensionFactory,
    //     //     $attributeFactory,
    //     //     $paymentData,
    //     //     $scopeConfig,
    //     //     $logger
    //     // );
    //     /** @var \Magento\Checkout\Model\Cart _cart */
    //     $this->_cart = $cart;
    // }

    protected $_code = "tuna";
    protected $_cart;

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

    public function getStandardCheckoutPaymentUrl()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart'); 
        return $cart->getQuote()->getStore()->getUrl("tunagateway/payment/request");
    }
}

