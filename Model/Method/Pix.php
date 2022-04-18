<?php

namespace AditumPayment\Magento2\Model\Method;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Psr\Log\LoggerInterface;

class Pix extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'aditumpix';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var
     */
    protected $_countryFactory;

    /**
     * @var null
     */
    protected $_minAmount = null;

    /**
     * @var null
     */
    protected $_maxAmount = null;

    /**
     * @var string[]
     */
    protected $_supportedCurrencyCodes = ['BRL'];

    /**
     * @var string
     */
    protected $_infoBlockType = \AditumPayment\Magento2\Block\Info\Pix::class;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $adminSession;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \AditumPayment\Magento2\Helper\Api
     */
    protected $api;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $resourceConnection;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \AditumPayment\Magento2\Helper\Api $api,
        \Magento\Backend\Model\Auth\Session $adminSession,
        \Psr\Log\LoggerInterface $mlogger,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->api = $api;
        $this->adminSession = $adminSession;
        $this->logger = $mlogger;
        $this->_scopeConfig = $scopeConfig;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return $this|Pix
     * @throws \Magento\Framework\Validator\Exception
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->logger->info('Inside Order');
        $this->logger->info(json_encode($payment->getAdditionalInformation(), true));
        $order = $payment->getOrder();
        if (!$result = $this->api->createOrderPix($order, $payment)) {
            $message = 'Houve um erro processando seu pedido. Por favor entre em contato conosco.';
            $this->messageManager
                ->addError($message);
            throw new \Magento\Framework\Validator\Exception(__($message));
        }

        if (!isset($result['status']) || $result['status'] !== "PreAuthorized"
            && $result['status'] !== "Authorized") {
            $message = 'Houve um erro processando seu pedido. Por favor entre em contato conosco.';
            $this->messageManager->addError($message);
            throw new \Magento\Framework\Validator\Exception(__($message));
        }
        $this->updateOrderRaw($order->getIncrementId());
        $order->setExtOrderId(str_replace("-", "", $result['charge']['id']));
        $order->addStatusHistoryComment('ID Aditum: '.$result['charge']['id']);
        $payment->setAdditionalInformation('uuid', $result['charge']['id']);
        $payment->setAdditionalInformation('aditumNumber', $result['charge']['transactions'][0]['aditumNumber']);
        $payment->setAdditionalInformation('qrCode', $result['charge']['transactions'][0]['qrCode']);
        $this->storeQrCode($result['charge']['transactions'][0]['qrCodeBase64'], $order->getIncrementId());
        $payment->setAdditionalInformation('bankIssuerId', $result['charge']['transactions'][0]['bankIssuerId']);
        $payment->setAdditionalInformation('status', $result['status']);
        if ($result['status'] == "Authorized") {
            $this->invoiceOrder($order);
        }
        return $this;
    }

    /**
     * @param $qrCodeBase64
     * @param $incrementId
     * @return void
     */
    public function storeQrCode($qrCodeBase64, $incrementId)
    {
        $qrcode = base64_decode($qrCodeBase64);
        $mediapath = $this->filesystem
            ->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath();
        $imageDir = $mediapath . "/aditumpix";
        if (!file_exists($imageDir)) {
            mkdir($imageDir);
        }
        $fileName = $imageDir . "/" . $incrementId . ".png";
        file_put_contents($fileName, $qrcode);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        if (!$this->_scopeConfig->getValue('payment/aditum/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            return false;
        }
        if (!$this->_scopeConfig->getValue(
            'payment/aditum_pix/enable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return false;
        }
        $isAvailable = $this->getConfigData('active', $quote ? $quote->getStoreId() : null);
        if (!$isAvailable) {
            return false;
        }
        return true;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount): Pix
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }
        try {
            // to do
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
        }
        $payment
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        return $this;
    }

    /**
     * @param $incrementId
     * @return void
     */
    public function updateOrderRaw($incrementId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('sales_order');
        $sql = "UPDATE " . $tableName . " SET status = 'pending', state = 'new' WHERE entity_id = " . $incrementId;
        $connection->query($sql);
    }

    /**
     * @param $order
     * @return void
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

}
