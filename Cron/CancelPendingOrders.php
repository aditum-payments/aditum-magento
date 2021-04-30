<?php

namespace AditumPayment\Magento2\Cron;

class CancelPendingOrders
{
    protected $logger;
    protected $collectionFactory;
    protected $orderFactory;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->orderFactory = $orderFactory;
    }
    public function execute()
    {

        $to = date('Y-m-d H:i:s',time()-3600);

        $orderCollection = $this->collectionFactory->create()->addFieldToSelect(array('*'));
        $orderCollection->addFieldToFilter('created_at', array('lteq' => $to));
        $orderCollection->getSelect()
            ->join(
                ["sop" => "sales_order_payment"],
                'main_table.entity_id = sop.parent_id',
                array('method')
            )
            ->where('sop.method = ?','pix' );
//        $orderCollection->setOrder(
//            'created_at',
//            'desc'
//        );
        foreach($orderCollection as $item) {
            $order = $this->orderFactory->create()->load($item->getId());
            $order->cancel()->save();
            $this->logger->info("Aditum: automatic cancel expired order ID: ".$order->getId());
        }
    }
}
