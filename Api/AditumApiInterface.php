<?php

namespace AditumPayment\Magento2\Api;

interface AditumApiInterface
{
    /**
     * GET for Post api
     * @param string $param
     * @return \AditumPayment\Magento2\Api\Data\BrandInterface
     */

    public function getCcBrand(string $ccNumber);
}
