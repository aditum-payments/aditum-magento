<?php

namespace AditumPayment\Magento2\Model\Config;

class AntifraudType implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => __('ClearSale')],
            ['value' => 2, 'label' => __('Konduto')],
        ];
    }
}
