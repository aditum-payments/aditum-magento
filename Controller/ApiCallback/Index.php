<?php

namespace AditumPayment\Magento2\Controller\ApiCallback;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Payment\Collection as PaymentCollection;
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Psr\Log\LoggerInterface;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var Http
     */
    protected $_request;

    /**
     * @var OrderRepository
     */
    protected $_orderRepository;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var CreditmemoFactory
     */
    protected $_creditmemoFactory;

    /**
     * @var TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ResultFactory
     */
    protected $result;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $paymentCollectionFactory;

    /**
     * @param Context $context
     * @param Http $request
     * @param OrderRepository $orderRepository
     * @param InvoiceService $invoiceService
     * @param CreditmemoFactory $creditmemoFactory
     * @param TransactionFactory $transactionFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $result
     * @param CollectionFactory $orderCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Http $request,
        OrderRepository $orderRepository,
        InvoiceService $invoiceService,
        CreditmemoFactory $creditmemoFactory,
        TransactionFactory $transactionFactory,
        LoggerInterface $logger,
        ResultFactory $result,
        CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        PaymentCollectionFactory $paymentCollectionFactory,
        array $data = []
    ) {
        $this->_request = $request;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_creditmemoFactory = $creditmemoFactory;
        $this->_transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->result = $result;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->paymentCollectionFactory = $paymentCollectionFactory;

        parent::__construct($context);
    }

    /**
     * @return false|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $this->logger->log("INFO", "Aditum Callback starting...");
        $json = file_get_contents('php://input');
        $this->logger->info("Aditum callback: " . $json);
        if ($auth = $this->authError()) {
            $this->logger->log("INFO", "Aditum Callback Auth Error...");
            return $auth;
        }
        try {
            if (!$this->isJson($json)) {
                $this->logger->info("ERROR: Aditum Callback is not json");
                $result = $this->resultRaw();
                $result->setHttpResponseCode(400);
                return $result;
            }
            $input = json_decode($json, true);
            $this->logger->info("Aditum Callback received ChargeStatus: " . $input['ChargeStatus'] . " for ChargeId: " . $input['ChargeId']);
            $order = $this->getOrderByChargeId($input['ChargeId']);

            // sanitize sensible datas
            $addInfo = $order->getPayment()->getAdditionalInformation();
            unset($addInfo['cc_number']);
            unset($addInfo['cc_cid']);
            $order->getPayment()->setAdditionalInformation($addInfo);
            // end

            $i = 0;
            if (!$order) {
                $this->logger->info("Aditum Callback order not found: " . $input['ChargeId']);
                return $this->orderNotFound();
            }

            while (!$this->getPayment($order->getEntityId())->getAdditionalInformation('order_created')) {
                $this->logger->info("Aditum Callback waiting for order creation...");
                sleep(1);
                $i++;
                if ($i >= 30) {
                    $this->logger->info("Aditum Callback timeout...");
                    $result = $this->resultRaw();
                    $result->setHttpResponseCode(200);
                    return $result;
                }
            }
            if ($input['ChargeStatus'] === 1) {

                $this->logger->info("Aditum Callback invoicing Magento order " . $order->getIncrementId());
                if (!$order->hasInvoices()) {
                    $order->getPayment()->setAdditionalInformation('status', 'Authorized');
                    $order->getPayment()->setAdditionalInformation('callbackStatus', 'Authorized');
                    $this->logger->info("Aditum Callback generating invoice for order " . $order->getIncrementId());
                    $this->invoiceOrder($order);
                    $this->logger->info("Aditum Callback invoice generated successfully for order " . $order->getIncrementId());

                    // Debug: Verificar se realmente foi gerada
                    $invoiceCount = $order->getInvoiceCollection()->count();
                    $this->logger->info("Aditum Debug: Order " . $order->getIncrementId() . " now has " . $invoiceCount . " invoices");
                } else {
                    $this->logger->info("Aditum Callback order " . $order->getIncrementId() . " already has invoices - skipping invoice generation");
                }
            } elseif ($input['ChargeStatus'] === 2) {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'PreAuthorized');
                $this->logger->log("INFO", "Aditum Callback status PreAuthorized.");
                return $this->resultRaw("");
            } elseif ($input['ChargeStatus'] === 4) {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'Canceled');
                $this->logger->log("INFO", "Aditum Callback status canceled");
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
                return $this->resultRaw("");
            } elseif ($input['ChargeStatus'] === 8) {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'Expired');
                $this->logger->log("INFO", "Aditum Callback status expired - cancelling order " . $order->getIncrementId());
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
                return $this->resultRaw("");
            } elseif ($order->getPayment()->getAdditionalInformation('status') !== 'NotAuthorized') {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'NotAuthorized');
                $this->logger->log("INFO", "Aditum Callback status other - cancelling. " . $order->getIncrementId());
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
            }

            return $this->resultRaw("");
        } catch (\Throwable $t) {
            $this->logger->error("An unexpected error was raised while handling the webhook request.");
            $this->logger->error($t->getMessage());
            $this->logger->error($t->getTraceAsString());
            $result = $this->resultRaw();
            $result->setHttpResponseCode(500);
            return $result;
        }
    }

    /**
     * Get payment by order id
     *
     * @param int $orderId
     * @return OrderPaymentInterface
     */
    public function getPayment(int $orderId): OrderPaymentInterface
    {
        /** @var PaymentCollection $paymentCollection */
        $paymentCollection = $this->paymentCollectionFactory->create();
        $paymentCollection->addFieldToFilter('parent_id', ['eq' => $orderId]);
        return $paymentCollection->getFirstItem();
    }

    /**
     * @return false|\Magento\Framework\Controller\ResultInterface
     */
    public function authError()
    {
        $merchantToken = $this->scopeConfig->getValue(
            'payment/aditum/client_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $headers = $this->getRequestHeaders();

        $this->logger->info("Header: " . json_encode($headers));

        if (!isset($headers['X-Aditum-Authorization'])) {
            $this->logger->info('Header nao existe');
            return $this->resultUnauthorized();
        }

        if ($headers['X-Aditum-Authorization'] != base64_encode($merchantToken)) {
            $this->logger->info("Base64 token: " . base64_encode($merchantToken));
            $this->logger->info('Header diferente');
            return $this->resultUnauthorized();
        }

        return false;
    }

    /**
     * @return array
     */
    function getRequestHeaders()
    {
        $headers = array();

        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(
                ' ',
                '-',
                ucwords(str_replace('_', ' ', strtolower(substr($key, 5))))
            );
            $headers[$header] = $value;
        }

        return $headers;
    }

    /**
     * @param $chargeId
     * @return false|\Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderByChargeId($chargeId)
    {
        $chargeId = str_replace("-", "", $chargeId);
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addAttributeToFilter('ext_order_id', array('eq' => $chargeId));
        $orderCollection->addAttributeToSelect('*');

        if (!$orderCollection->count()) {
            return false;
        }

        foreach ($orderCollection as $item) {
            $order = $this->_orderRepository->get($item->getId());
            return $order;
        }
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function orderNotFound()
    {
        $result = $this->resultRaw();
        $result->setHttpResponseCode(204);
        return $result;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function resultUnauthorized()
    {
        $result = $this->resultRaw();
        $result->setHttpResponseCode(401);
        return $result;
    }

    /**
     * @param string $txt
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function resultRaw($txt = "")
    {
        $resultEmpty = $this->result->create(ResultFactory::TYPE_RAW);
        $resultEmpty->setContents("");
        return $resultEmpty;
    }

    /**
     * @param $order
     */
    public function cancelOrder($order)
    {
        try {
            $currentState = $order->getState();
            $this->logger->info("Aditum Callback attempting to cancel order " . $order->getIncrementId() . " with current state: " . $currentState);

            // Verificar se o pedido pode ser cancelado
            if (!$order->canCancel()) {
                $this->logger->warning("Aditum Callback: Order " . $order->getIncrementId() . " cannot be canceled (state: " . $currentState . ")");

                // Se tem fatura, criar credit memo (estorno)
                if ($order->hasInvoices() && $order->getState() === 'processing') {
                    $this->logger->info("Aditum Callback: Order " . $order->getIncrementId() . " has invoices, attempting refund instead of cancel");
                    $this->refundOrder($order);
                    return;
                }

                $this->logger->info("Aditum Callback: Order " . $order->getIncrementId() . " cannot be canceled or refunded");
                return;
            }

            // Cancelar normalmente
            $order->cancel()->save();
            $this->logger->info("Aditum Callback: Order " . $order->getIncrementId() . " canceled successfully");

        } catch (\Exception $e) {
            $this->logger->error("Aditum Callback Error canceling order " . $order->getIncrementId() . ": " . $e->getMessage());
            $this->logger->error("Aditum Callback Error stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Create refund (credit memo) for orders that cannot be canceled
     * @param $order
     */
    public function refundOrder($order)
    {
        try {
            // Para pedidos com fatura, criar credit memo (estorno)
            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                if ($invoice->canRefund()) {
                    $this->logger->info("Aditum Callback: Creating refund for invoice " . $invoice->getIncrementId() . " (Order: " . $order->getIncrementId() . ")");

                    // Criar credit memo usando CreditmemoFactory
                    $creditmemo = $this->_creditmemoFactory->createByInvoice($invoice);
                    if ($creditmemo) {
                        // Definir estado do credit memo
                        $creditmemo->setState(\Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED);

                        // Salvar o credit memo usando transação
                        $transaction = $this->_transactionFactory->create()
                            ->addObject($creditmemo)
                            ->addObject($creditmemo->getOrder());
                        $transaction->save();

                        $this->logger->info("Aditum Callback: Credit Memo created successfully - ID: " . $creditmemo->getIncrementId() . " with state REFUNDED");

                        // Adicionar comentário no histórico do pedido
                        $order->addCommentToStatusHistory(
                            'Estorno criado automaticamente via Aditum Callback (ChargeStatus = Canceled/Expired). Credit Memo: ' . $creditmemo->getIncrementId()
                        )->setIsCustomerNotified(false);

                        // Atualizar o pedido para closed
                        $order->setState(\Magento\Sales\Model\Order::STATE_CLOSED);
                        $order->setStatus('closed');
                        $order->save();

                        $this->logger->info("Aditum Callback: Order " . $order->getIncrementId() . " updated to 'closed' status");
                        return;
                    }
                }
            }

            $this->logger->warning("Aditum Callback: No refundable invoices found for order " . $order->getIncrementId());

        } catch (\Exception $e) {
            $this->logger->error("Aditum Callback Error creating refund for order " . $order->getIncrementId() . ": " . $e->getMessage());
            $this->logger->error("Aditum Callback Error stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * @param $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
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

    /**
     * @param $string
     * @return bool
     */
    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
