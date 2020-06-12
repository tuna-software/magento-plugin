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
        $output['AMAZON_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/AMAZON.png');
        $output['AMEX_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/AMEX.png');
        $output['CIRRUS_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/CIRRUS.png');
        $output['DINNERS_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DINNERS.png');
        $output['DIRECT_DEBIT_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DIRECT DEBIT.png');
        $output['DISCOVER_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/DISCOVER.png');
        $output['EBAY_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/EBAY.png');
        $output['EWAY_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/EWAY.png');
        $output['JCB_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/JCB.png');
        $output['MAESTRO_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MAESTRO.png');
        $output['MASTERCARD_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/MASTERCARD.png');
        $output['PAYPAL_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/PAYPAL.png');
        $output['SAGE_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SAGE.png');
        $output['SHOPIFY_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SHOPIFY.png');
        $output['SKRILL_MONEYBOOKERS_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SKRILL MONEYBOOKERS.png');
        $output['SKRILL_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SKRILL.png');
        $output['SOLO_2_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SOLO 2.png');
        $output['SOLO_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/SOLO.png');
        $output['VISA_ELECTRON_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/VISA ELECTRON.png');
        $output['VISA_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/VISA.png');
        $output['WESTERN_UNION_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/WESTERN UNION.png');
        $output['WORLDPAY_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/WORLDPAY.png');
        $output['W_flag'] = $this->getViewFileUrl('Tuna_TunaGateway::images/W.png');

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
