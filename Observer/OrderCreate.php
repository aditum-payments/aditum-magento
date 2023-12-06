<?php

declare(strict_types=1);

namespace AditumPayment\Magento2\Observer;

use AditumPayment\Magento2\Api\Data\AditumConfigInterface;
use AditumPayment\Magento2\Api\Data\PaymentAdditionalInformationInterface as AdditionalInfo;
use AditumPayment\Magento2\Model\Method\Boleto;
use AditumPayment\Magento2\Model\Method\CreditCard;
use AditumPayment\Magento2\Model\Method\Pix;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class OrderCreate implements ObserverInterface
{
    /**
     * @var OrderInterface
     */
    protected OrderInterface $order;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var InvoiceService
     */
    protected InvoiceService $invoiceService;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var TransactionFactory
     */
    protected TransactionFactory $transactionFactory;

    /**
     * @param OrderInterface $order
     * @param ScopeConfigInterface $scopeConfig
     * @param InvoiceService $invoiceService
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        OrderInterface $order,
        ScopeConfigInterface $scopeConfig,
        InvoiceService $invoiceService,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        TransactionFactory $transactionFactory
    ) {
        $this->order = $order;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceService = $invoiceService;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * Main observer execution
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $this->logger->info('Aditum starting create order observer...');

        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        //  Magento 2.2.* compatibility
        if (!$order) {
            $orderids = $observer->getEvent()->getOrderIds();
            foreach ($orderids as $orderid) {
                $order = $this->order->load($orderid);
            }
        }
        $order = $this->orderRepository->get($order->getId());
        $payment = $order->getPayment();
        $method = $payment->getMethod();

        // sanitize sensible data
        $addInfo = $payment->getAdditionalInformation();
        unset($addInfo[AdditionalInfo::CC_NUMBER]);
        unset($addInfo[AdditionalInfo::CC_CID]);
        $payment->setAdditionalInformation($addInfo);
        // end

        $statusNew = $this->scopeConfig->getValue(AditumConfigInterface::ORDER_STATUS_NEW, ScopeInterface::SCOPE_STORE);

        if ($method === CreditCard::CODE) {
            $this->logger->info('Observer - ' . $method);
            if ($payment->getAdditionalInformation(AdditionalInfo::STATUS) === AdditionalInfo::STATUS_PRE_AUTHORIZED
                && $payment->getAdditionalInformation(AdditionalInfo::CALLBACK_STATUS)
                !== AdditionalInfo::STATUS_AUTHORIZED) {
                $this->logger->info('Aditum observer - set new');
                $order->setState(Order::STATE_NEW)->setStatus($statusNew);
                $order->save();
            }
            if ($payment->getAdditionalInformation(AdditionalInfo::STATUS) === AdditionalInfo::STATUS_AUTHORIZED
                && $payment->getAdditionalInformation(AdditionalInfo::CALLBACK_STATUS)
                !== AdditionalInfo::STATUS_NOT_AUTHORIZED) {
                if (!$order->hasInvoices()) {
                    $this->invoiceOrder($order);
                }
            }
            if ($payment->getAdditionalInformation(AdditionalInfo::STATUS) === AdditionalInfo::STATUS_NOT_AUTHORIZED
                && $payment->getAdditionalInformation(AdditionalInfo::CALLBACK_STATUS)
                === AdditionalInfo::STATUS_NOT_AUTHORIZED) {
                if ($order->getState() !== Order::STATE_CANCELED) {
                    $this->cancelOrder($order);
                }
            }
            $payment->setAdditionalInformation(AdditionalInfo::ORDER_CREATED, '1');
            $payment->save();
        } elseif ($method === Boleto::CODE || $method === Pix::CODE) {
            $order->setState(Order::STATE_NEW)->setStatus($statusNew);
            $payment->setAdditionalInformation(AdditionalInfo::ORDER_CREATED, '1');
            $order->save();
            $this->logger->info('Aditum observer - order_created status set.');
        }
    }

    /**
     * Invoice order
     *
     * @param $order
     * @return void
     * @throws LocalizedException
     */
    public function invoiceOrder($order): void
    {
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $transaction = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
        /** @todo implement status processing config */
        $order->setState(Order::STATE_PROCESSING)->setStatus('processing');
        $order->save();
    }

    /**
     * Cancel order
     *
     * @param OrderInterface $order
     * @return void
     * @throws \Exception
     */
    public function cancelOrder(OrderInterface $order): void
    {
        $order->cancel()->save();
    }
}
