<?php

namespace AditumPayment\Magento2\Controller\ApiCallback;

use \Magento\Framework\App\CsrfAwareActionInterface;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\App\Request\InvalidRequestException;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $_request;
    protected $_orderRepository;
    protected $_invoiceService;
    protected $_transactionFactory;
    protected $logger;
    protected $result;
    protected $orderCollectionFactory;
    protected $scopeConfig;

    public function __construct(\Magento\Framework\App\Action\Context $context,
                                \Magento\Framework\App\Request\Http $request,
                                \Magento\Sales\Model\OrderRepository $orderRepository,
                                \Magento\Sales\Model\Service\InvoiceService $invoiceService,
                                \Magento\Framework\DB\TransactionFactory $transactionFactory,
                                \Psr\Log\LoggerInterface $logger,
                                \Magento\Framework\Controller\ResultFactory $result,
                                \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                array $data = []
    )
    {
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
    public function execute()
    {
        $this->logger->log("INFO", "Aditum Callback starting...");
        $json = file_get_contents('php://input');
        $this->logger->info("Aditum callback: ".$json);
        if($auth = $this->authError()){
            $this->logger->log("INFO", "Aditum Callback Auth Error...");
            return $auth;
        }
        try {
            if (!$this->isJson($json)) {
                $this->logger->info("ERROR: Aditum Callback is not json");
                return $this->resultRaw("");
            }
            $input = json_decode($json, true);
            $order = $this->getOrderByChargeId($input['ChargeId']);
            if(!$order){
                $this->logger->info("Aditum Callback order not found: ".$input['ChargeId']);
                return $this->orderNotFound();
            }
            if($input['ChargeStatus']===1){
                $this->logger->info("Aditum Callback invoicing Magento order " . $order->getIncrementId());
                $order->getPayment()->setAdditionalInformation('status','Authorized');
                $order->getPayment()->setAdditionalInformation('callbackStatus','Authorized');
                if(!$order->hasInvoices()) {
                    $this->invoiceOrder($order);
                }
            } else if($input['ChargeStatus']===2){
                $order->getPayment()->setAdditionalInformation('callbackStatus','PreAuthorized');
                $this->logger->log("INFO", "Aditum Callback status PreAuthorized.");
                return $this->resultRaw("");
            }
            else {
                $order->getPayment()->setAdditionalInformation('callbackStatus','NotAuthorized');
                $this->logger->log("INFO", "Aditum Callback status other - cancelling. ".$order->getIncrementId());
                $this->cancelOrder($order);
                return $this->resultRaw("");
            }
        } catch (Exception $e)
        {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTrace());
            $result = $this->resultRaw();
            $result->setHttpResponseCode(400);
            return $result;
        }
        $this->logger->log("INFO", "Aditum Callback ended.");
        return $this->resultRaw();
    }
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
    public function orderNotFound()
    {
        $result = $this->resultRaw();
        $result->setHttpResponseCode(404);
        return $result;
    }
    public function resultUnauthorized()
    {
        $result = $this->resultRaw();
        $result->setHttpResponseCode(401);
        return $result;
    }
    public function resultRaw($txt="")
    {
        $resultEmpty = $this->result->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $resultEmpty->setContents("");
        return $resultEmpty;
    }

    public function cancelOrder($order)
    {
        $order->cancel()->save();
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
