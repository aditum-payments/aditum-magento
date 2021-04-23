<?php

namespace AditumPayment\Magento2\Model\Method;

use Magento\Directory\Helper\Data as DirectoryHelper;

class DebitCard extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'aditum_dc';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_countryFactory;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = ['BRL'];
    protected $_infoBlockType = \AditumPayment\Magento2\Block\Info::class;
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    protected $adminSession;
    protected $messageManager;
    protected $api;
    protected $logger;
    protected $_scopeConfig;

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
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig,
            $logger, $resource, $resourceCollection, $data, $directory);
        $this->api = $api;
        $this->adminSession = $adminSession;
        $this->logger = $mlogger;
        $this->_scopeConfig = $scopeConfig;
    }

    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->logger->info('Inside Order');

        $order = $payment->getOrder();
        if (!$pixreturn = $this->api->createOrder($order)) {
            throw new \Magento\Framework\Validator\Exception(__('Houve um erro processando seu pedido. Por favor entre em contato conosco.'));
        }
        $this->updateOrderRaw($order->getIncrementId());
        $order->setExtOrderId($pixreturn);
        $order->addStatusHistoryComment('ID PIX: '.$pixreturn);
        $payment->setAdditionalInformation('pix_id',$pixreturn);
        $payment->setAdditionalInformation('pix_redirect_url',$this->api->getOrderRedirectUrl($pixreturn));
        return $this;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if(!$this->_scopeConfig->getValue('payment/aditum/enable',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return false;
        }
        if(!$this->_scopeConfig->getValue('payment/aditum/enable_dc',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return false;
        }
        if ($this->adminSession->getUser()) {
            return false;
        }
        $isAvailable = $this->getConfigData('active', $quote ? $quote->getStoreId() : null);
        if (empty($quote)) {
            return $isAvailable;
        }
        if ($this->getConfigData("group_restriction") == false) {
            return $isAvailable;
        }
        $currentGroupId = $quote->getCustomerGroupId();
        $customerGroups = explode(',', $this->getConfigData("customer_groups"));

        if ($isAvailable && in_array($currentGroupId, $customerGroups)) {
            return true;
        }
        return false;
    }
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }
        try {
            // call API - refund
            // executa aqui refund da API
//            if ($returnXml === null) {
//                $errorMsg = 'Impossível gerar reembolso. Algo deu errado.';
//                throw new \Magento\Framework\Validator\Exception($errorMsg);
//            }
        } catch (\Exception $e) {
//            $this->debugData(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
        }
        $payment
            ->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        return $this;
    }
    // Gambi master force update order
    public function updateOrderRaw($incrementId){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order');
        $sql = "UPDATE " . $tableName . " SET status = 'pending', state = 'new' WHERE entity_id = " . $incrementId;
        $connection->query($sql);
    }
}
