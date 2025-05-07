<?php

namespace Tuna\TunaGateway\Block;

use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\Serialize\Serializer\Json as JsonHelper;
use Magento\Framework\View\Element\Template\Context;

class TunaImages extends \Magento\Framework\View\Element\Template
{
    protected $assetRepository;
    protected $jsonHelper;

    public function __construct(
        Context $context,
        AssetRepository $assetRepository,
        JsonHelper $jsonHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->assetRepository = $assetRepository;
        $this->jsonHelper = $jsonHelper;
    }

    /**
     * @return array
     */
    public function fillCardFlags()
    {
        $output = [];
        $output['AMAZON'] = $this->getViewFileUrl('Tuna_TunaGateway::images/AMAZON.png');
        $output['AMEX'] = $this->getViewFileUrl('Tuna_TunaGateway::images/AMEX.png');
        $output['CIRRUS'] = $this->getViewFileUrl('Tuna_TunaGateway::images/CIRRUS.png');
        $output['DINNERS'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DINNERS.png');
        $output['DIRECTDEBIT'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DIRECT_DEBIT.png');
        $output['DISCOVER'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DISCOVER.png');
        $output['EBAY'] = $this->getViewFileUrl('Tuna_TunaGateway::images/EBAY.png');
        $output['EWAY'] = $this->getViewFileUrl('Tuna_TunaGateway::images/EWAY.png');
        $output['JCB'] = $this->getViewFileUrl('Tuna_TunaGateway::images/JCB.png');
        $output['MAESTRO'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MAESTRO.png');
        $output['MASTERCARD'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MASTERCARD.png');
        $output['MASTER'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MASTERCARD.png');
        $output['PAYPAL'] = $this->getViewFileUrl('Tuna_TunaGateway::images/PAYPAL.png');
        $output['SAGE'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SAGE.png');
        $output['SHOPIFY'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SHOPIFY.png');
        $output['SKRILLMONEYBOOKERS'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SKRILL_MONEYBOOKERS.png');
        $output['SKRILL'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SKRILL.png');
        $output['SOLO2'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SOLO_2.png');
        $output['SOLO'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SOLO.png');
        $output['VISAELECTRON'] = $this->getViewFileUrl('Tuna_TunaGateway::images/VISA_ELECTRON.png');
        $output['VISA'] = $this->getViewFileUrl('Tuna_TunaGateway::images/VISA.png');
        $output['WESTERNUNION'] = $this->getViewFileUrl('Tuna_TunaGateway::images/WESTERN_UNION.png');
        $output['WORLDPAY'] = $this->getViewFileUrl('Tuna_TunaGateway::images/WORLDPAY.png');
        $output['W'] = $this->getViewFileUrl('Tuna_TunaGateway::images/W.png');

        return $output;
    }

    /**
     * @return JsonHelper
     */
    public function getJsonHelper() : JsonHelper
    {
        return $this->jsonHelper;
    }

    /**
     * @param $fileId
     * @param array $params
     * @return string
     */
    public function getViewFileUrl($fileId, array $params = []) : ?string
    {
        // $params = array_merge(['_secure' => $this->request->isSecure()], $params);
        // $params = ['_secure' => $this->_getRequest()->isSecure()];
        $params = ['_secure' => true];
        return $this->assetRepository->getUrlWithParams($fileId, $params);
    }
}
