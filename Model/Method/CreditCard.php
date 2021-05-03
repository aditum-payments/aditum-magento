<?php

namespace AditumPayment\Magento2\Model\Method;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Payment\Observer\AbstractDataAssignObserver;


class CreditCard extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'aditumcc';

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
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    protected $adminSession;
    protected $messageManager;
    protected $api;
    protected $logger;
    protected $_scopeConfig;

    public function __construct(\Magento\Framework\Model\Context $context,
                                \Magento\Framework\Registry $registry,
                                \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
                                \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
                                \Magento\Payment\Helper\Data $paymentData,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Payment\Model\Method\Logger $logger,
                                \Magento\Framework\Module\ModuleListInterface $moduleList,
                                \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
                                \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
                                \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
                                \AditumPayment\Magento2\Helper\Api $api,
                                \Magento\Backend\Model\Auth\Session $adminSession,
                                \Psr\Log\LoggerInterface $mlogger,
                                array $data = [])
    {
        $this->api = $api;
        $this->adminSession = $adminSession;
        $this->mlogger = $mlogger;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData,
            $scopeConfig, $logger, $moduleList, $localeDate, $resource, $resourceCollection, $data);
    }

//    protected $_formBlockType = \AditumPayment\Magento2\Block\Form\CreditCard::class;
//    protected $_infoBlockType = \AditumPayment\Magento2\Block\Payment\InfoCreditCard::class;

//    public function __construct(
//        \Magento\Framework\Model\Context $context,
//        \Magento\Framework\Registry $registry,
//        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
//        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
//        \Magento\Payment\Helper\Data $paymentData,
//        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
//        \Magento\Payment\Model\Method\Logger $logger,
//        \AditumPayment\Magento2\Helper\Api $api,
//        \Magento\Backend\Model\Auth\Session $adminSession,
//        \Psr\Log\LoggerInterface $mlogger,
//        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
//        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
//        array $data = [],
//        DirectoryHelper $directory = null
//    ) {
//        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig,
//            $logger, $resource, $resourceCollection, $data, $directory);
//        $this->api = $api;
//        $this->adminSession = $adminSession;
//        $this->logger = $mlogger;
//        $this->_scopeConfig = $scopeConfig;
//    }

    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->mlogger->info('Inside Order');
        $info = $this->getInfoInstance();
        $payment->getAdditionalInformation('fullname');
        $preAuth = $this->_scopeConfig->getValue('payment/aditum_cc/pre_auth', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $order = $payment->getOrder();
        try {
            if (!$aditumreturn = $this->api->createOrderCc($order, $info, $payment, $preAuth)) {
                throw new \Magento\Framework\Validator\Exception(__('Houve um erro processando seu pedido. Por favor entre em contato conosco.'));
            }
            if (!$preAuth) {
                if ($aditumreturn['charge']['chargeStatus'] != 'Authorized') {
                    throw new \Magento\Framework\Validator\Exception(__('Houve um erro cobrando o cartão. Por favor verifique os dados.'));
                }
            } else {
                if ($aditumreturn['status'] != 'PreAuthorized') {
                    throw new \Magento\Framework\Validator\Exception(__('Houve um erro cobrando o cartão. Por favor verifique os dados.'));
                }
            }
        } catch (Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__('Houve um erro processando seu pedido. Por favor entre em contato conosco.'));
        }
        $this->updateOrderRaw($order->getIncrementId(),$aditumreturn);
        $order->setExtOrderId($aditumreturn['charge']['id']);
        $order->addStatusHistoryComment(
            'ID Aditum: '.$aditumreturn['charge']['id']."<br>\n"
            .'Cartão: '.$aditumreturn['charge']['transactions'][0]['card']['cardNumber']."<br>\n");
        $payment->setAdditionalInformation('aditum_id',$aditumreturn['charge']['id']);
        $payment->setAdditionalInformation('aditum_nsu',$aditumreturn['charge']['nsu']);
        return $this;
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $this->_eventManager->dispatch(
            'payment_method_assign_data_' . $this->getCode(),
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE => $data
            ]
        );
        $this->_eventManager->dispatch(
            'payment_method_assign_data',
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE => $data
            ]
        );
        return $this;
    }


//    public function capture()
//    {
//
//    }
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if(!$this->_scopeConfig->getValue('payment/aditum/enable',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return false;
        }
        if(!$this->_scopeConfig->getValue('payment/aditum_cc/enable',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return false;
        }
        $isAvailable = $this->getConfigData('active', $quote ? $quote->getStoreId() : null);
        if(!$isAvailable) return false;
        return true;
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
            ->setTransactionId( '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
//            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        return $this;
    }
    // Gambi master force update order

//    public function getCcAvailableTypes()
//    {
//        return ['SO','SM','VI','MC','AE','DI','DN','UN','JCB','MI','MD','ELO','AU'];
//    }
//    public function getCcAvailableTypesValues()
//    {
//        return [
//            //Solo, Switch or Maestro. International safe
//            'SO' => '/(^(6334)[5-9](\d{11}$|\d{13,14}$))|(^(6767)(\d{12}$|\d{14,15}$))/',
//            'SM' => '/(^(5[0678])\d{11,18}$)|(^(6[^05])\d{11,18}$)|(^(601)[^1]\d{9,16}$)|(^(6011)\d{9,11}$)' .
//                '|(^(6011)\d{13,16}$)|(^(65)\d{11,13}$)|(^(65)\d{15,18}$)' .
//                '|(^(49030)[2-9](\d{10}$|\d{12,13}$))|(^(49033)[5-9](\d{10}$|\d{12,13}$))' .
//                '|(^(49110)[1-2](\d{10}$|\d{12,13}$))|(^(49117)[4-9](\d{10}$|\d{12,13}$))' .
//                '|(^(49118)[0-2](\d{10}$|\d{12,13}$))|(^(4936)(\d{12}$|\d{14,15}$))/',
//            // Visa
//            'VI' => '/^4[0-9]{12}([0-9]{3})?$/',
//            // Master Card
//            'MC' => '/^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$/',
//            // American Express
//            'AE' => '/^3[47][0-9]{13}$/',
//            // Discover
//            'DI' => '/^(6011((0|9|[2-4])[0-9]{11,14}|(74|7[7-9]|8[6-9])[0-9]{10,13})|6(4[4-9][0-9]{13,16}|' .
//                '5[0-9]{14,17}))/',
//            'DN' => '/^3(0[0-5][0-9]{13,16}|095[0-9]{12,15}|(6|[8-9])[0-9]{14,17})/',
//            // UnionPay
//            'UN' => '/^622(1(2[6-9][0-9]{10,13}|[3-9][0-9]{11,14})|[3-8][0-9]{12,15}|9([[0-1][0-9]{11,14}|' .
//                '2[0-5][0-9]{10,13}))|62[4-6][0-9]{13,16}|628[2-8][0-9]{12,15}/',
//            // JCB
//            'JCB' => '/^35(2[8-9][0-9]{12,15}|[3-8][0-9]{13,16})/',
//            'MI' => '/^(5(0|[6-9])|63|67(?!59|6770|6774))\d*$/',
//            'MD' => '/^(6759(?!24|38|40|6[3-9]|70|76)|676770|676774)\d*$/',
//
//            //Hipercard
//            'HC' => '/^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))\d*$/',
//            //Elo
//            'ELO' => '/^((509091)|(636368)|(636297)|(504175)|(438935)|(40117[8-9])|(45763[1-2])|' .
//                '(457393)|(431274)|(50990[0-2])|(5099[7-9][0-9])|(50996[4-9])|(509[1-8][0-9][0-9])|' .
//                '(5090(0[0-2]|0[4-9]|1[2-9]|[24589][0-9]|3[1-9]|6[0-46-9]|7[0-24-9]))|' .
//                '(5067(0[0-24-8]|1[0-24-9]|2[014-9]|3[0-379]|4[0-9]|5[0-3]|6[0-5]|7[0-8]))|' .
//                '(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|' .
//                '(6504(8[5-9]|9[0-9])|6505(0[0-9]|1[0-9]|2[0-9]|3[0-8]))|' .
//                '(6505(4[1-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|' .
//                '(6507(0[0-9]|1[0-8]))|(65072[0-7])|(6509(0[1-9]|1[0-9]|20))|' .
//                '(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[0-9]))|' .
//                '(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8])))\d*$/',
//            //Aura
//            'AU' => '/^5078\d*$/'
//        ];
//    }
    public function updateOrderRaw($incrementId){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order');
        $sql = "UPDATE " . $tableName . " SET status = 'pending', state = 'new' WHERE entity_id = " . $incrementId;
        $connection->query($sql);
    }
    public function validate()
    {
        return true;
        $info = $this->getInfoInstance();
        $errorMsg = false;
//        $availableTypes = explode(',', $this->getConfigData('cctypes'));

        $ccNumber = $info->getCcNumber();

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        $ccType = '';

        if (in_array($info->getCcType(), $availableTypes)) {
            if ($this->validateCcNum(
                    $ccNumber
                ) || $this->otherCcType(
                    $info->getCcType()
                ) && $this->validateCcNumOther(
                // Other credit card type number validation
                    $ccNumber
                )
            ) {
                $ccTypeRegExpList = [
                    //Solo, Switch or Maestro. International safe
                    'SO' => '/(^(6334)[5-9](\d{11}$|\d{13,14}$))|(^(6767)(\d{12}$|\d{14,15}$))/',
                    'SM' => '/(^(5[0678])\d{11,18}$)|(^(6[^05])\d{11,18}$)|(^(601)[^1]\d{9,16}$)|(^(6011)\d{9,11}$)' .
                        '|(^(6011)\d{13,16}$)|(^(65)\d{11,13}$)|(^(65)\d{15,18}$)' .
                        '|(^(49030)[2-9](\d{10}$|\d{12,13}$))|(^(49033)[5-9](\d{10}$|\d{12,13}$))' .
                        '|(^(49110)[1-2](\d{10}$|\d{12,13}$))|(^(49117)[4-9](\d{10}$|\d{12,13}$))' .
                        '|(^(49118)[0-2](\d{10}$|\d{12,13}$))|(^(4936)(\d{12}$|\d{14,15}$))/',
                    // Visa
                    'VI' => '/^4[0-9]{12}([0-9]{3})?$/',
                    // Master Card
                    'MC' => '/^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$/',
                    // American Express
                    'AE' => '/^3[47][0-9]{13}$/',
                    // Discover
                    'DI' => '/^(6011((0|9|[2-4])[0-9]{11,14}|(74|7[7-9]|8[6-9])[0-9]{10,13})|6(4[4-9][0-9]{13,16}|' .
                        '5[0-9]{14,17}))/',
                    'DN' => '/^3(0[0-5][0-9]{13,16}|095[0-9]{12,15}|(6|[8-9])[0-9]{14,17})/',
                    // UnionPay
                    'UN' => '/^622(1(2[6-9][0-9]{10,13}|[3-9][0-9]{11,14})|[3-8][0-9]{12,15}|9([[0-1][0-9]{11,14}|' .
                        '2[0-5][0-9]{10,13}))|62[4-6][0-9]{13,16}|628[2-8][0-9]{12,15}/',
                    // JCB
                    'JCB' => '/^35(2[8-9][0-9]{12,15}|[3-8][0-9]{13,16})/',
                    'MI' => '/^(5(0|[6-9])|63|67(?!59|6770|6774))\d*$/',
                    'MD' => '/^(6759(?!24|38|40|6[3-9]|70|76)|676770|676774)\d*$/',

                    //Hipercard
                    'HC' => '/^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))\d*$/',
                    //Elo
                    'ELO' => '/^((509091)|(636368)|(636297)|(504175)|(438935)|(40117[8-9])|(45763[1-2])|' .
                        '(457393)|(431274)|(50990[0-2])|(5099[7-9][0-9])|(50996[4-9])|(509[1-8][0-9][0-9])|' .
                        '(5090(0[0-2]|0[4-9]|1[2-9]|[24589][0-9]|3[1-9]|6[0-46-9]|7[0-24-9]))|' .
                        '(5067(0[0-24-8]|1[0-24-9]|2[014-9]|3[0-379]|4[0-9]|5[0-3]|6[0-5]|7[0-8]))|' .
                        '(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|' .
                        '(6504(8[5-9]|9[0-9])|6505(0[0-9]|1[0-9]|2[0-9]|3[0-8]))|' .
                        '(6505(4[1-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|' .
                        '(6507(0[0-9]|1[0-8]))|(65072[0-7])|(6509(0[1-9]|1[0-9]|20))|' .
                        '(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[0-9]))|' .
                        '(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8])))\d*$/',
                    //Aura
                    'AU' => '/^5078\d*$/'
                ];

                $ccNumAndTypeMatches = isset(
                        $ccTypeRegExpList[$info->getCcType()]
                    ) && preg_match(
                        $ccTypeRegExpList[$info->getCcType()],
                        $ccNumber
                    );
                $ccType = $ccNumAndTypeMatches ? $info->getCcType() : 'OT';

                if (!$ccNumAndTypeMatches && !$this->otherCcType($info->getCcType())) {
                    $errorMsg = __('The credit card number doesn\'t match the credit card type.');
                }
            } else {
                $errorMsg = __('Invalid Credit Card Number');
            }
        } else {
            $errorMsg = __('This credit card type is not allowed for this payment method.');
        }

        //validate credit card verification number
        if ($errorMsg === false && $this->hasVerification()) {
            $verifcationRegEx = $this->getVerificationRegEx();
            $regExp = isset($verifcationRegEx[$info->getCcType()]) ? $verifcationRegEx[$info->getCcType()] : '';
            if (!$info->getCcCid() || !$regExp || !preg_match($regExp, $info->getCcCid())) {
                $errorMsg = __('Please enter a valid credit card verification number.');
            }
        }

        if ($ccType != 'SS' && !$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
            $errorMsg = __('Please enter a valid credit card expiration date.');
        }

        if ($errorMsg) {
            throw new \Magento\Framework\Exception\LocalizedException($errorMsg);
        }

        return $this;
    }
}
