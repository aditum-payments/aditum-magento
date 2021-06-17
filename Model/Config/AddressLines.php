<?php

namespace AditumPayment\Magento2\Model\Config;

class AddressLines implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Line 0')],
            ['value' => 1, 'label' => __('Line 1')],
            ['value' => 2, 'label' => __('Line 2')],
            ['value' => 3, 'label' => __('Line 3')],
            ['value' => 4, 'label' => __('Line 4')],
        ];
    }
}
