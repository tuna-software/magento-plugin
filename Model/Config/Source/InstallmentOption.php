<?php

namespace Tuna\TunaGateway\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class InstallmentOption implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('1x')],
            ['value' => '2', 'label' => __('2x')],
            ['value' => '3', 'label' => __('3x')],
            ['value' => '4', 'label' => __('4x')],
            ['value' => '5', 'label' => __('5x')],
            ['value' => '6', 'label' => __('6x')],
            ['value' => '7', 'label' => __('7x')],
            ['value' => '8', 'label' => __('8x')],
            ['value' => '9', 'label' => __('9x')],
            ['value' => '10', 'label' => __('10x')],
            ['value' => '11', 'label' => __('11x')],
            ['value' => '12', 'label' => __('12x')]
        ];
    }
}