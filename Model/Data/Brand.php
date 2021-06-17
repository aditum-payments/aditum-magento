<?php

namespace AditumPayment\Magento2\Model\Data;

class Brand extends \Magento\Framework\Model\AbstractModel implements \AditumPayment\Magento2\Api\Data\BrandInterface
{
    /**
     * @inheritDoc
     */
    public function getBrand(): string
    {
        return $this->getData(self::BRAND);
    }

    /**
     * @inheritDoc
     */
    public function setBrand(string $brand)
    {
        $this->setData(self::BRAND, $brand);
    }
}
