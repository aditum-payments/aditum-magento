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
    protected $_orderRepository;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        $this->_order = $order;
        $this->_invoiceService = $invoiceService;
        $this->_orderRepository = $orderRepository;
        $this->_transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->logger->info("Entrou no observer");
        $order = $observer->getEvent()->getOrder();
        //  Magento 2.2.* compatibility
        if (!$order) {
            $orderids = $observer->getEvent()->getOrderIds();
            foreach ($orderids as $orderid) {
                $order = $this->_order->load($orderid);
            }
        }
        $order = $this->_orderRepository->get($order->getId());
        $payment = $order->getPayment();
        $method = $payment->getMethod();
        if ($method=="aditumcc") {
            $this->logger->info("Observer - aditumcc");
            if ($payment->getAdditionalInformation('status')=='PreAuthorized'
                && $payment->getAdditionalInformation('callbackStatus') !== 'Authorized') {
                $this->logger->info("Observer - set new");
                $order->setState('new')->setStatus('pending'); /// corrigir //////////////////////////
                $order->save();
            }
            if ($payment->getAdditionalInformation('status')=='Authorized'
                && $payment->getAdditionalInformation('callbackStatus')!=='NotAuthorized') {
                if (!$order->hasInvoices()) {
                    $this->invoiceOrder($order);
                }
            }
            if ($payment->getAdditionalInformation('status')=='NotAuthorized'
                && $payment->getAdditionalInformation('callbackStatus') !== 'NotAuthorized') {
                if ($order->getState()!='canceled') {
                    $this->cancelOrder($order);
                }
            }
            $payment->setAdditionalInformation('order_created', '1');
            $payment->save();
        }
        if ($method=="aditumboleto") {
            $order->setState('new')->setStatus('pending');
            $payment->setAdditionalInformation('order_created', '1');
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
    public function cancelOrder($order)
    {
        $order->cancel()->save();
    }
}
