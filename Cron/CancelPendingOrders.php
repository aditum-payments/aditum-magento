<?php

namespace AditumPayment\Magento2\Cron;

class CancelPendingOrders
{
    protected $logger;
    protected $collectionFactory;
    protected $orderFactory;
    protected $scopeConfig;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->orderFactory = $orderFactory;
        $this->scopeConfig = $scopeConfig;
    }
    public function execute()
    {
        $expires_in = $this->scopeConfig->getValue('payment/aditum/order_expiration_days',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if(!$expires_in) return;
        $to_cc = date('Y-m-d H:i:s',time()-86400*$expires_in);
        $orderCollection = $this->getOrderCollection($to_cc,'aditumcc');
        $this->cancelOrders($orderCollection);
        $to_boleto = date('Y-m-d H:i:s',time()-86400*($expires_in+5));
        $orderCollection = $this->getOrderCollection($to_boleto,'aditumboleto');
        $this->cancelOrders($orderCollection);
    }
    public function getOrderCollection($to,$method)
    {
        $orderCollection = $this->collectionFactory->create()->addFieldToSelect(array('*'));
        $orderCollection->addFieldToFilter('created_at', array('lteq' => $to));
        $orderCollection->addFieldToFilter('state', array('eq' => 'new'));
        $orderCollection->getSelect()
            ->join(
                ["sop" => "sales_order_payment"],
                'main_table.entity_id = sop.parent_id',
                array('method')
            )
            ->where('sop.method = ?',[$method] );
        return $orderCollection;
    }
    public function cancelOrders($orderCollection)
    {
        foreach($orderCollection as $item) {
            $order = $this->orderFactory->create()->load($item->getId());
            $order->cancel()->save();
            $this->logger->info("Aditum: automatic cancel expired order ID: ".$order->getId());
        }
    }
}
