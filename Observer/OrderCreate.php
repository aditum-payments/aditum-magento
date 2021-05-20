<?php

namespace AditumPayment\Magento2\Observer;

use GumNet\AME\Helper\SensediaAPI;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderCreate implements ObserverInterface
{
    protected $_order;
protected $_invoiceService;
protected $_transactionFactory;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    )
    {
        $this->_order = $order;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        //  Magento 2.2.* compatibility
        if(!$order){
            $orderids = $observer->getEvent()->getOrderIds();
            foreach($orderids as $orderid){
                $order = $this->_order->load($orderid);
            }
        }
        $payment = $order->getPayment();
        $method = $payment->getMethod();
        if($method=="aditumcc") {
            if($payment->getAdditionalInformation('status')=='PreAuthorized'
                &&!$payment->getAdditionalInformation('callbackStatus') === 'Authorized') {
                $order->setState('new')->setStatus('pending');
                $order->save();
            }
            if($payment->getAdditionalInformation('status')=='Authorized'
                &&!$payment->getAdditionalInformation('callbackStatus')){
                if(!$order->hasInvoices()){
                    $this->invoiceOrder($order);
                }
            }
        }
        if($method=="aditumboleto") {
            $order->setState('new')->setStatus('pending');
            $order->save();
        }
    }
    public function invoiceOrder($order)
    {
        $invoice = $this->_invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $transaction = $this->_transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
        $order->setState('processing')->setStatus('processing');
        $order->save();
    }
}
