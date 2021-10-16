<?php

namespace Tuna\TunaGateway\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class FeeConfiguration implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'S', 'label' => __('Simple')],
            ['value' => 'C', 'label' => __('compound')],
        ];
    }
}