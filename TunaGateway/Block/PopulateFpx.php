<?php

namespace Tuna\TunaGateway\Block;

use Magento\Framework\View\Asset\Repository as AssetRepository;

class PopulateFpx extends \Magento\Framework\View\Element\Template
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

    public function getFpxConfig()
    {
        $output = [];
        $output['fpxLogoImageUrl'] = $this->getViewFileUrl('Tuna_TunaGateway::images/fpx_logo.png');

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
