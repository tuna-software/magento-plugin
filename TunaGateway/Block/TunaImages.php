<?php

namespace Tuna\TunaGateway\Block;

use Magento\Framework\View\Asset\Repository as AssetRepository;

class TunaImages extends \Magento\Framework\View\Element\Template
{
    protected $assetRepository;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        AssetRepository $assetRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->assetRepository = $assetRepository;
    }

    public function fillCardFlags()
    {
        $output = [];
        $output['Amazon'] = $this->getViewFileUrl('Tuna_TunaGateway::images/AMAZON.png');
        $output['Amex'] = $this->getViewFileUrl('Tuna_TunaGateway::images/AMEX.png');
        $output['Cirrus'] = $this->getViewFileUrl('Tuna_TunaGateway::images/CIRRUS.png');
        $output['Dinners'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DINNERS.png');
        $output['DirectDebit'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DIRECT DEBIT.png');
        $output['Discover'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DISCOVER.png');
        $output['Ebay'] = $this->getViewFileUrl('Tuna_TunaGateway::images/EBAY.png');
        $output['Eway'] = $this->getViewFileUrl('Tuna_TunaGateway::images/EWAY.png');
        $output['Jcb'] = $this->getViewFileUrl('Tuna_TunaGateway::images/JCB.png');
        $output['Maestro'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MAESTRO.png');
        $output['MasterCard'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MASTERCARD.png');
        $output['Paypal'] = $this->getViewFileUrl('Tuna_TunaGateway::images/PAYPAL.png');
        $output['Sage'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SAGE.png');
        $output['Shopify'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SHOPIFY.png');
        $output['SkrillMoneyBookers'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SKRILL MONEYBOOKERS.png');
        $output['Skrill'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SKRILL.png');
        $output['Solo2'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SOLO 2.png');
        $output['Solo'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SOLO.png');
        $output['VisaElectron'] = $this->getViewFileUrl('Tuna_TunaGateway::images/VISA ELECTRON.png');
        $output['Visa'] = $this->getViewFileUrl('Tuna_TunaGateway::images/VISA.png');
        $output['WesternUnion'] = $this->getViewFileUrl('Tuna_TunaGateway::images/WESTERN UNION.png');
        $output['WorldPay'] = $this->getViewFileUrl('Tuna_TunaGateway::images/WORLDPAY.png');
        $output['W'] = $this->getViewFileUrl('Tuna_TunaGateway::images/W.png');

        return $output;
    }

    public function getViewFileUrl($fileId, array $params = [])
    {
        // $params = array_merge(['_secure' => $this->request->isSecure()], $params);
        // $params = ['_secure' => $this->_getRequest()->isSecure()];
        $params = ['_secure' => true];
        return $this->assetRepository->getUrlWithParams($fileId, $params);
    }
}
