<?php

namespace Tuna\TunaGateway\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class EndpointConfiguration implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'production', 'label' => __('Production')],
            ['value' => 'sandbox', 'label' => __('Sandbox')],
        ];
    }
}