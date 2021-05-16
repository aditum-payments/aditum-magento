<?php

namespace AditumPayment\Magento2\Helper;

class Api
{
    public $enableExternalExtension = true;

    protected $url;
    protected $scopeConfig;
    protected $logger;
    protected $_storeManager;
    protected $checkoutSession;

    public function __construct(\Magento\Framework\App\Helper\Context $context,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Psr\Log\LoggerInterface $logger,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->_storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
    }
    public function createOrderBoleto(\Magento\Sales\Model\Order\Interceptor $order,$payment)
    {
        return $this->extCreateOrderBoleto($order,$payment);
    }
    public function extCreateOrderBoleto(\Magento\Sales\Model\Order\Interceptor $order,$payment)
    {
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl(\AditumPayments\ApiSDK\Configuration::DEV_URL);
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::setlog(false);
        \AditumPayments\ApiSDK\Configuration::login();

        $gateway = new \AditumPayments\ApiSDK\Gateway;
        $boleto = new \AditumPayments\ApiSDK\Domains\Boleto;

        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();
        $boleto->setDeadline($this->scopeConfig->getValue('payment/aditum_boleto/expiration_days',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)); // >>>>>>>>>>>>>>>>>>

// Customer
        $boleto->customer->setId($order->getIncrementId());
        $boleto->customer->setName($payment->getAdditionalInformation('boletofullname'));
        $boleto->customer->setEmail($quote->getCustomerEmail());
        $cpfCnpj = $payment->getAdditionalInformation('boletodocument');
        $cpfCnpj = filter_var($cpfCnpj, FILTER_SANITIZE_NUMBER_INT);

        if(strlen($cpfCnpj)==14) {
            $boleto->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CNPJ);
        }
        else{
            $boleto->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CPF);
        }
        $boleto->customer->setDocument($cpfCnpj);

// Customer->address
        $boleto->customer->address->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $boleto->customer->address->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $boleto->customer->address->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $boleto->customer->address->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);

        $boleto->customer->address->setCity($billingAddress->getCity());
        $boleto->customer->address->setState($this->codigoUF($billingAddress->getRegion()));
        $boleto->customer->address->setCountry("BR");
        $boleto->customer->address->setZipcode($billingAddress->getPostcode());

// Customer->phone
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $boleto->customer->phone->setCountryCode("55");
        $boleto->customer->phone->setAreaCode(substr($phone_number,0,2));
        $boleto->customer->phone->setNumber(substr($phone_number,2));
        $boleto->customer->phone->setType(\AditumPayments\ApiSDK\Enum\PhoneType::MOBILE);

// Transactions
        $grandTotal = (int)$order->getGrandTotal() * 100;
        $boleto->transactions->setAmount($grandTotal);
        $boleto->transactions->setInstructions("Senhor caixa não receber após o vencimento.");

// Transactions->fine (opcional)
        if($this->scopeConfig->getValue("payment/aditum_boleto/fine_days")) {
            $boleto->transactions->fine
                ->setStartDate($this->scopeConfig->getValue("payment/aditum_boleto/fine_days"));
        }
        if($this->scopeConfig->getValue("payment/aditum_boleto/fine_days")
            &&$this->scopeConfig->getValue("payment/aditum_boleto/fine_amount")){
            $boleto->transactions->fine
                ->setAmount($this->scopeConfig->getValue("payment/aditum_boleto/fine_amount"));
        }
        if($this->scopeConfig->getValue("payment/aditum_boleto/fine_days")
            &&$this->scopeConfig->getValue("payment/aditum_boleto/fine_percent")) {
            $boleto->transactions->fine->setInterest(10);
        }

// Transactions->discount (opcional)
//        $boleto->transactions->discount->setType(AditumPayments\ApiSDK\Enum\DiscountType::FIXED);
//        $boleto->transactions->discount->setAmount(200);
//        $boleto->transactions->discount->setDeadline("1");

        $result = $gateway->charge($boleto);

        $this->logger->info("External Apitum API Return: ".json_encode($result));
        return $result;
    }
    public function createOrderCc(\Magento\Sales\Model\Order\Interceptor $order, $info, $payment, $preAuth = 0)
    {
        return $this->extCreateOrderCc($order,$info,$payment,$preAuth);
    }
    public function extCreateOrderCc(\Magento\Sales\Model\Order\Interceptor $order,$info,$payment, $preAuth=0)
    {
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl($this->getApiUrl());
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::login();
        \AditumPayments\ApiSDK\Configuration::setlog(false);

        $gateway = new \AditumPayments\ApiSDK\Gateway;
        $authorization = new \AditumPayments\ApiSDK\Domains\Authorization;
//        if($preAuth){
//            unset($authorization);
//            $authorization = new \AditumPayments\ApiSDK\Domains\PreAuthorization;
//        }
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();
        $this->logger->info("Card CCDC Type: ".$payment->getAdditionalInformation('cc_dc_choice'));
        if($payment->getAdditionalInformation('cc_dc_choice')=="dc"){
            $authorization->transactions->setPaymentType(\AditumPayments\ApiSDK\Enum\PaymentType::DEBIT);
        }
        else {
            $authorization->transactions->setPaymentType(\AditumPayments\ApiSDK\Enum\PaymentType::CREDIT);
        }
        $authorization->transactions->setAcquirer(\AditumPayments\ApiSDK\Enum\AcquirerCode::SIMULADOR);
        $authorization->customer->setName($order->getBillingAddress()->getName());
        $authorization->customer->setEmail($quote->getCustomerEmail());

        $cpfCnpj = $payment->getAdditionalInformation('document');
        $cpfCnpj = filter_var($cpfCnpj, FILTER_SANITIZE_NUMBER_INT);

        if(strlen($cpfCnpj)==14) {
            $authorization->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CNPJ);
        }
        else{
            $authorization->customer->setDocumentType(\AditumPayments\ApiSDK\Enum\DocumentType::CPF);
        }
        $authorization->customer->setDocument($cpfCnpj);

        $authorization->customer->phone->setCountryCode("55");
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $authorization->customer->phone->setAreaCode(substr($phone_number,0,2));
        $authorization->customer->phone->setNumber(substr($phone_number,2));
        $authorization->customer->phone->setType(\AditumPayments\ApiSDK\Enum\PhoneType::MOBILE);
        $authorization->customer->address->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $authorization->customer->address->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $authorization->customer->address->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $authorization->customer->address->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);
        $authorization->customer->address->setCity($billingAddress->getCity());
        $authorization->customer->address->setState($this->codigoUF($billingAddress->getRegion()));
        $authorization->customer->address->setCountry("BR");
        $authorization->customer->address->setZipcode($billingAddress->getPostcode());
        $authorization->transactions->card
            ->setCardNumber(preg_replace('/[\-\s]+/', '', $info->getCcNumber()));
        $authorization->transactions->card->setCVV($payment->getAdditionalInformation('cc_cid'));
        $authorization->transactions->card->setCardholderName($payment->getAdditionalInformation('fullname'));
        $authorization->transactions->card->setExpirationMonth($payment->getAdditionalInformation('cc_exp_month'));
        $authorization->transactions->card->setExpirationYear($payment->getAdditionalInformation('cc_exp_year'));

        $authorization->transactions->card->billingAddress->setStreet($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")]);
        $authorization->transactions->card->billingAddress->setNumber($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")]);
        $authorization->transactions->card->billingAddress->setComplement($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")]);
        $authorization->transactions->card->billingAddress->setNeighborhood($billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")]);
        $authorization->transactions->card->billingAddress->setCity($billingAddress->getCity());
        $authorization->transactions->card->billingAddress->setState($this->codigoUF($billingAddress->getRegion()));
        $authorization->transactions->card->billingAddress->setCountry("BR");
        $authorization->transactions->card->billingAddress->setZipcode($billingAddress->getPostcode());

        if($payment->getAdditionalInformation('cc_dc_choice')!="dc") {
            $authorization->transactions->setInstallmentNumber($payment->getAdditionalInformation('installments'));
        }
        $grandTotal = (int)$order->getGrandTotal() * 100;
        $authorization->transactions->setAmount($grandTotal);

        $result = $gateway->charge($authorization);

        $this->logger->info("External Apitum API Return: ".json_encode($result));
        return $result;
    }
    public function getApiUrl()
    {
        if(!$this->scopeConfig->getValue('payment/aditum/environment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return \AditumPayments\ApiSDK\Configuration::DEV_URL;
        }
        return \AditumPayments\ApiSDK\Configuration::PROD_URL;
    }
    public function logError($action,$url,$output,$input="")
    {
        $this->logger->error("Aditum Request error: ".$action." - ".$url." - ".$input." - ".$output);
        return false;
    }
    public function getClientId()
    {
        return $this->scopeConfig->getValue('payment/aditum/client_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function getClientSecret(){
        return $this->scopeConfig->getValue('payment/aditum/client_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function codigoUF($txt_uf)
    {
        $array_ufs = array("Rondônia" => "RO",
            "Acre" => "AC",
            "Amazonas" => "AM",
            "Roraima" => "RR",
            "Pará" => "PA",
            "Amapá" => "AP",
            "Tocantins" => "TO",
            "Maranhão" => "MA",
            "Piauí" => "PI",
            "Ceará" => "CE",
            "Rio Grande do Norte" => "RN",
            "Paraíba" => "PB",
            "Pernambuco" => "PE",
            "Alagoas" => "AL",
            "Sergipe" => "SE",
            "Bahia" => "BA",
            "Minas Gerais" => "MG",
            "Espírito Santo" => "ES",
            "Rio de Janeiro" => "RJ",
            "São Paulo" => "SP",
            "Paraná" => "PR",
            "Santa Catarina" => "SC",
            "Rio Grande do Sul (*)" => "RS",
            "Mato Grosso do Sul" => "MS",
            "Mato Grosso" => "MT",
            "Goiás" => "GO",
            "Distrito Federal" => "DF");
        $uf = "RJ";
        foreach ($array_ufs as $key => $value) {
            if ($key == $txt_uf) {
                $uf = $value;
                break;
            }
        }
        return $uf;
    }
    public function getDocumentTypeId($type="cpf"){
        if($type=="cpf"){
            return 1;
        }
        if($type=="cnpj"){
            return 2;
        }
        return 1;
    }
    public function getError($arrayReturn)
    {
        if(!isset($arrayReturn['httpMsg'])) return "";
        $httpMsg = json_decode($arrayReturn['httpMsg'],true);
        foreach($httpMsg['errors'] as $error){
            $saida[] = $error['message'];
        }
        $this->logger->info("Erro: ".$saida[0]);
        return $saida[0];
    }
    public function getBoletoUrl($result)
    {
        $env = $this->scopeConfig->getValue('payment/aditum/environment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if($env){
            $url = \AditumPayments\ApiSDK\Configuration::PROD_URL;
        }
        else{
            $url = \AditumPayments\ApiSDK\Configuration::DEV_URL;
        }
        $url = str_replace("/v2/","",$url);
        $bankSlipUrl = str_replace("\\","",$result['charge']['transactions'][0]['bankSlipUrl']);
        return $url . $bankSlipUrl;
    }
}
