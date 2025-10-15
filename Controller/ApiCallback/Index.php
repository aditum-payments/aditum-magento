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
use Psr\Log\LoggerInterface;
use AditumPayment\Magento2\Logger\ApiLogger;

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
     * @var TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ApiLogger
     */
    protected $apiLogger;

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
     * @param TransactionFactory $transactionFactory
     * @param LoggerInterface $logger
     * @param ApiLogger $apiLogger
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
        TransactionFactory $transactionFactory,
        LoggerInterface $logger,
        ApiLogger $apiLogger,
        ResultFactory $result,
        CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        PaymentCollectionFactory $paymentCollectionFactory,
        array $data = []
    ) {
        $this->_request = $request;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->apiLogger = $apiLogger;
        $this->result = $result;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->paymentCollectionFactory = $paymentCollectionFactory;

        parent::__construct($context);
    }

    /**
     * Log to API-specific log file
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function logApi($level, $message, $context = [])
    {
        if ($this->apiLogger) {
            switch ($level) {
                case 'info':
                    $this->apiLogger->info($message, $context);
                    break;
                case 'error':
                    $this->apiLogger->error($message, $context);
                    break;
                case 'warning':
                    $this->apiLogger->warning($message, $context);
                    break;
                case 'debug':
                    $this->apiLogger->debug($message, $context);
                    break;
                default:
                    $this->apiLogger->info($message, $context);
            }
        } else {
            // Fallback to system logger with API prefix
            $this->logger->info('[ADITUM API] ' . $message, $context);
        }
    }

    /**
     * @return false|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $this->logApi('info', "Aditum Callback starting...");
        $json = file_get_contents('php://input');
        $this->logApi('info', "Aditum callback: " . $json);
        if ($auth = $this->authError()) {
            $this->logApi('info', "Aditum Callback Auth Error...");
            return $auth;
        }
        try {
            if (!$this->isJson($json)) {
                $this->logApi('error', "ERROR: Aditum Callback is not json");
                $result = $this->resultRaw();
                $result->setHttpResponseCode(400);
                return $result;
            }
            $input = json_decode($json, true);
            $order = $this->getOrderByChargeId($input['ChargeId']);

            // sanitize sensible datas
            $addInfo = $order->getPayment()->getAdditionalInformation();
            unset($addInfo['cc_number']);
            unset($addInfo['cc_cid']);
            $order->getPayment()->setAdditionalInformation($addInfo);
            // end

            $i = 0;
            if (!$order) {
                $this->logApi('info', "Aditum Callback order not found: " . $input['ChargeId']);
                return $this->orderNotFound();
            }

            while (!$this->getPayment($order->getEntityId())->getAdditionalInformation('order_created')) {
                $this->logApi('info', "Aditum Callback waiting for order creation...");
                sleep(1);
                $i++;
                if ($i >= 30) {
                    $this->logApi('info', "Aditum Callback timeout...");
                    $result = $this->resultRaw();
                    $result->setHttpResponseCode(200);
                    return $result;
                }
            }
            if ($input['ChargeStatus'] === 1) {

                $this->logApi('info', "Aditum Callback invoicing Magento order " . $order->getIncrementId());
                if (!$order->hasInvoices()) {
                    $order->getPayment()->setAdditionalInformation('status', 'Authorized');
                    $order->getPayment()->setAdditionalInformation('callbackStatus', 'Authorized');
                    $this->invoiceOrder($order);
                }
            } elseif ($input['ChargeStatus'] === 2) {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'PreAuthorized');
                $this->logApi('info', "Aditum Callback status PreAuthorized.");
                return $this->resultRaw("");
            } elseif ($input['ChargeStatus'] === 4) {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'Canceled');
                $this->logApi('info', "Aditum Callback status canceled");
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
                return $this->resultRaw("");
            } elseif ($order->getPayment()->getAdditionalInformation('status') !== 'NotAuthorized') {

                $order->getPayment()->setAdditionalInformation('callbackStatus', 'NotAuthorized');
                $this->logApi('info', "Aditum Callback status other - cancelling. " . $order->getIncrementId());
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
            }

            return $this->resultRaw("");
        } catch (\Throwable $t) {
            $this->logApi('error', "An unexpected error was raised while handling the webhook request.");
            $this->logApi('error', $t->getMessage());
            $this->logApi('error', $t->getTraceAsString());
            $result = $this->resultRaw();
            $result->setHttpResponseCode(500);
            return $result;
        }

        $this->logApi('info', "Aditum Callback ended.");
        return $this->resultRaw();
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

        $this->logApi('info', "Header: " . json_encode($headers));

        if (!isset($headers['X-Aditum-Authorization'])) {
            $this->logApi('info', 'Header nao existe');
            return $this->resultUnauthorized();
        }

        if ($headers['X-Aditum-Authorization'] != base64_encode($merchantToken)) {
            $this->logApi('info', "Base64 token: " . base64_encode($merchantToken));
            $this->logApi('info', 'Header diferente');
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
        $order->cancel()->save();
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
