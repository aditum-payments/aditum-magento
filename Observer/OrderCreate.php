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
    protected $logger;


    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    )
    {
        $this->_order = $order;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->logger->info("Entrou no observer");
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
            $this->logger->info("Observer - aditumcc");
            if($payment->getAdditionalInformation('status')=='PreAuthorized'
                &&$payment->getAdditionalInformation('callbackStatus') !== 'Authorized') {
                $this->logger->info("Observer - set new");
                $order->setState('new')->setStatus('pending'); /// corrigir
                $order->save();
            }
            if($payment->getAdditionalInformation('status')=='Authorized'
                &&$payment->getAdditionalInformation('callbackStatus')!=='NotAuthorized'){
                if(!$order->hasInvoices()){
                    $this->logger->info("Observer - invoice order");
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
        $order->setState('processing')->setStatus('processing');// corrigir
        $order->save();
    }
}
