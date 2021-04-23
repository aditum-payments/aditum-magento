<?php
namespace AditumPayment\Magento2\Model\ResourceModel\Post;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'tatix_pix_order_collection';
    protected $_eventObject = 'order_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('AditumPayment\Magento2\Model\Order', 'AditumPayment\Magento2\Model\ResourceModel\Order');
    }
}
