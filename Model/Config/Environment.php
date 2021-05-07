<?php

namespace AditumPayment\Magento2\Model\Config;

class Environment implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Homologação')],
            ['value' => 1, 'label' => __('Produção')],
        ];
    }
}
