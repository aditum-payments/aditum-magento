<?php

namespace Aditum\Payment\Observer\Sales;

use Magento\Sales\Model\Order;

class OrderPlaceAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        /** @var Order $order */
        $order = $observer->getOrder();

        if ($order->getPayment()->getMethod() == "pix") {
            $order->setState('new')->setStatus('pending');
            $order->save();
        }
        $this->updateOrderRaw($order->getIncrementId());
    }
    public function updateOrderRaw($incrementId){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order');
        $sql = "UPDATE " . $tableName . " SET status = 'pending', state = 'new' WHERE increment_id = " . $incrementId;
        $connection->query($sql);
    }
}
