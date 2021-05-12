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
        $auth = false;
        if(isset($_SERVER['Authorization'])){
            $merchantToken = $this->scopeConfig->getValue('payment/aditum/client_secret',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if($_SERVER['Authorization']==base64_encode($merchantToken)){
                $auth = true;
            }
        }
        if(!$auth){
            return $this->resultUnauthorized();
        }
        try {
            $json = file_get_contents('php://input');
            if (!$this->isJson($json)) {
                $this->logger->info("ERROR: Aditum Callback is not json");
                return $this->resultRaw("");
            }
            $input = json_decode($json, true);
            $this->logger->info("Aditum callback: ".$json);
            $transaction = $input['Transactions'][0];
            if($transaction['ChargeStatus']==\AditumPayments\ApiSDK\Enum\ChargeStatus::AUTHORIZED){
                $orderCollection = $this->orderCollectionFactory->create();
                $orderCollection->addAttributeToFilter('ext_order_id',$input['ChargeId']);
                $orderCollection->addAttributeToSelect('*');
                $orderCollection->addAttributeToFilter('state','new');
                if(!$orderCollection->getTotalCount()) {
                    $this->logger->info("Aditum Callback order not found: ");
                    $this->logger->log("INFO", "Aditum Callback ended.");
                    return $this->orderNotFound();
                }
                foreach($orderCollection->fetchItem() as $item){
                    $order = $this->_orderRepository->get($item->getId());
                    $this->logger->info("Aditum Callback invoicing Magento order " . $order->getIncrementId());
                    $this->invoiceOrder($order);
                }
            } else if($transaction['ChargeStatus']==\AditumPayments\ApiSDK\Enum\ChargeStatus::PRE_AUTHORIZED){
                $this->logger->log("INFO", "Aditum Callback ended.");
                return $this->resultRaw("");
            }
            else {
                return $this->resultRaw("");
            }
        } catch (Exception $e)
        {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTrace());
            $result = $this->resultRaw();
            $result->setHttpResponseCode(400);
        }
        $this->logger->log("INFO", "Aditum Callback ended.");
        return $this->resultRaw();
    }
    public function orderNotFound()
    {
        return $this->resultRaw();
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
