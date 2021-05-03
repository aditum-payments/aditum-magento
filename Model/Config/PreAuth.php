<?php

namespace AditumPayment\Magento2\Model\Config;

class PreAuth implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Captura direta')],
            ['value' => 1, 'label' => __('Pré-autorização')],
        ];
    }
}
