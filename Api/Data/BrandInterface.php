<?php

namespace AditumPayment\Magento2\Api\Data;

interface BrandInterface
{
    const BRAND = 'brand';

    /**
     * @return string
     */
    public function getBrand(): string;

    /**
     * @param string $brand
     * @return  void
     */
    public function setBrand(string $brand);
}
