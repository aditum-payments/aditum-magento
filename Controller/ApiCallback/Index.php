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
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
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

    /**
     * @param Context $context
     * @param Http $request
     * @param OrderRepository $orderRepository
     * @param InvoiceService $invoiceService
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
        TransactionFactory $transactionFactory,
        LoggerInterface $logger,
        ResultFactory $result,
        CollectionFactory $orderCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->_request = $request;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->result = $result;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;

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
            $orderId = $order->getEntityId();
            while (!$this->_orderRepository->get($orderId)->getPayment()->getAdditionalInformation('order_created')) {
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
                    $this->invoiceOrder($order);
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

        $this->logger->log("INFO", "Aditum Callback ended.");
        return $this->resultRaw();
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
