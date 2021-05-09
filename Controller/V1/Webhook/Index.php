<?php

namespace AditumPayment\Magento2\Controller\V1\Webhook;

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
    protected $_storeManager;
    protected $result;
    protected $email;

    public function __construct(\Magento\Framework\App\Action\Context $context,
                                \Magento\Framework\App\Request\Http $request,
                                \Magento\Sales\Model\OrderRepository $orderRepository,
                                \Magento\Sales\Model\Service\InvoiceService $invoiceService,
                                \Magento\Framework\DB\TransactionFactory $transactionFactory,
                                \Psr\Log\LoggerInterface $logger,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Framework\Controller\ResultFactory $result,
                                \AditumPayment\Magento2\Helper\Email $email,
                                array $data = []
    )
    {
        $this->_request = $request;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->_storeManager = $storeManager;
        $this->result = $result;
        $this->email = $email;
        parent::__construct($context);
    }

    public function execute()
    {
        /*
{
  "email": "string",
  "webhooks": [
    {
      "targetUrl": "string",
      "eventType": 0,
      "customHeaders": [
        {
          "param": "string",
          "value": "string"
        }
      ]
    }
  ]
}'
         */


        $this->logger->log("INFO", "Aditum Callback starting...");
        $json = file_get_contents('php://input');

        if (!$this->isJson($json)) {
            $this->logger->info("ERROR: PIX Callback is not json");

        } else {
            $this->logger->info(json_encode($_SERVER,JSON_PRETTY_PRINT));
            $this->logger->info($json);

//            $input = json_decode($json, true);
//             verify if id exists
//            if (!array_key_exists('externalOrderId', $input)) {
//                $this->logger->log("ERROR", "PIX Callback PIX ID not found in JSON");
//            } else {
//                $incrId = $input['externalOrderId'];
//                if ($input['status'] == "APPROVED") {
//                    $this->logger->info("PIX Callback getting Magento Order for " . $incrId);
//                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//                    $orderInterface = $objectManager->create('Magento\Sales\Api\Data\OrderInterface');
//                    $order = $orderInterface->loadByIncrementId($incrId);
//                    $orderId = $order->getId();
//                    $this->logger->info("PIX callback Order ID: " . $orderId);
//                    $order = $this->_orderRepository->get($orderId);
//                    $this->email->sendEmail($order);
//                    $this->logger->log("INFO", "PIX Callback invoicing Magento order " . $incrId);
//                    $this->invoiceOrder($order);
//                } else {
//                    $this->logger->log("ERROR", "Wrong Order status: " . $input['status']);
//                }
//            }
        }
        $this->logger->log("INFO", "Aditum Callback ended.");
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
