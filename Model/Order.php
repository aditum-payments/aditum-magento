<?php
namespace Aditum\Payment\Model;

class Order extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'tatix_pix_order';

    protected $_cacheTag = 'tatix_pix_order';

    protected $_eventPrefix = 'tatix_pix_order';

    protected function _construct()
    {
        $this->_init('Aditum\Payment\Model\ResourceModel\Order');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }
}
