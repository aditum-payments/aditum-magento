<?php

namespace AditumPayment\Magento2\Controller\ApiCallback;

use \Magento\Framework\App\CsrfAwareActionInterface;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\App\Request\InvalidRequestException;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $_request;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $result;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Controller\ResultFactory $result
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Action\Context                      $context,
        \Magento\Framework\App\Request\Http                        $request,
        \Magento\Sales\Model\OrderRepository                       $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService                $invoiceService,
        \Magento\Framework\DB\TransactionFactory                   $transactionFactory,
        \Psr\Log\LoggerInterface                                   $logger,
        \Magento\Framework\Controller\ResultFactory                $result,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface         $scopeConfig,
        array                                                      $data = []
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
        $this->logger->info("Aditum callback: ".$json);
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
            if (!$order) {
                $this->logger->info("Aditum Callback order not found: ".$input['ChargeId']);
                return $this->orderNotFound();
            }
            $i=0;
            while (!$order->getPayment()->getAdditionalInformation('order_created')) {
                $this->logger->info("Aditum Callback waiting for order creation...");
                sleep(1);
                $i++;
                if ($i>=30) {
                    $this->logger->info("Aditum Callback timeout...");
                    $result = $this->resultRaw();
                    $result->setHttpResponseCode(200);
                    return $result;
                }
            }
            if ($input['ChargeStatus']===1) {
                $this->logger->info("Aditum Callback invoicing Magento order " . $order->getIncrementId());
                if (!$order->hasInvoices()) {
                    $order->getPayment()->setAdditionalInformation('status', 'Authorized');
                    $order->getPayment()->setAdditionalInformation('callbackStatus', 'Authorized');
                    $this->invoiceOrder($order);
                }
            } elseif ($input['ChargeStatus']===2) {
                $order->getPayment()->setAdditionalInformation('callbackStatus', 'PreAuthorized');
                $this->logger->log("INFO", "Aditum Callback status PreAuthorized.");
                return $this->resultRaw("");
            } elseif ($input['ChargeStatus']===4) {
                $order->getPayment()->setAdditionalInformation('callbackStatus', 'Canceled');
                $this->logger->log("INFO", "Aditum Callback status canceled");
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
                return $this->resultRaw("");
            } elseif ($order->getPayment()->getAdditionalInformation('status')!=='NotAuthorized') {
                $order->getPayment()->setAdditionalInformation('callbackStatus', 'NotAuthorized');
                $this->logger->log("INFO", "Aditum Callback status other - cancelling. " . $order->getIncrementId());
                if ($order->getState() !== "canceled") {
                    $this->cancelOrder($order);
                }
            }
            return $this->resultRaw("");

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTrace());
            $result = $this->resultRaw();
            $result->setHttpResponseCode(400);
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
        $merchantToken = $this->scopeConfig->getValue('payment/aditum/client_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $headers = $this->getRequestHeaders();
        $this->logger->info("Header: ". json_encode($headers));
        if(!isset($headers['X-Aditum-Authorization'])){
            $this->logger->info('Header nao existe');
            return $this->resultUnauthorized();
        }
        if($headers['X-Aditum-Authorization']!=base64_encode($merchantToken)){
            $this->logger->info("Base64 token: ".base64_encode($merchantToken));
            $this->logger->info('Header diferente');
            return $this->resultUnauthorized();
        }
        return false;
    }

    /**
     * @return array
     */
    function getRequestHeaders() {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-',
                ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
        return $headers;
    }

    /**
     * @param $chargeId
     * @return false|\Magento\Framework\Controller\ResultInterface|\Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderByChargeId($chargeId)
    {
        $chargeId = str_replace("-","",$chargeId);
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addAttributeToFilter('ext_order_id',array('eq' => $chargeId));
        $orderCollection->addAttributeToSelect('*');
//        $orderCollection->addAttributeToFilter('state',array('eq' => 'new'));
        if(!$orderCollection->count()) {
            $this->logger->info("Aditum Callback order not found: ".$chargeId);
            return $this->orderNotFound();
        }
        foreach($orderCollection as $item){
            $order = $this->_orderRepository->get($item->getId());
            return $order;
        }
        return false;
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
    public function resultRaw($txt="")
    {
        $resultEmpty = $this->result->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
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