<?php

namespace AditumPayment\Magento2\Model;

class AditumApi implements \AditumPayment\Magento2\Api\AditumApiInterface
{
    protected $api;
    protected $brand;

    public function __construct(
        \AditumPayment\Magento2\Helper\Api $api,
        \AditumPayment\Magento2\Api\Data\BrandInterface $brand
    ) {
        $this->api = $api;
        $this->brand = $brand;
    }

    /**
     * @inheritDoc
     */
    public function getCcBrand(string $ccNumber)
    {
        if (strlen($ccNumber<6)) {
            $this->brand->setBrand("");
            return $this->brand;
        }
        $brand = $this->api->getCcBrand($ccNumber);
        $this->brand->setBrand($brand);
        return $this->brand;
    }
}
