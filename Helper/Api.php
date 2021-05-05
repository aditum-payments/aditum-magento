<?php

namespace AditumPayment\Magento2\Helper;

class Api
{
    public $enableExternalExtension = true;

    protected $curl;
    protected $url;
    protected $scopeConfig;
    protected $logger;
    protected $dbAditum;
    protected $_storeManager;
    protected $checkoutSession;

    public function __construct(\Magento\Framework\App\Helper\Context $context,
                                \Magento\Framework\HTTP\Client\Curl $curl,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Psr\Log\LoggerInterface $logger,
                                \AditumPayment\Magento2\Helper\DbAditum $dbAditum,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->dbAditum = $dbAditum;
        $this->_storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;

        $env = $this->scopeConfig->getValue('payment/aditum/environment', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($env == 0) {
            $this->url = "https://payment-dev.aditum.com.br/v2";
        }
        else if ($env == 1) {
            $this->url = "https://payment.aditum.com.br/v2";
        }
    }
    public function createOrderBoleto(\Magento\Sales\Model\Order\Interceptor $order,$info)
    {
        $url = $this->url . "/charge/authorization";
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();

        $tTime = time()+86400*(int)$this->_scopeConfig->getValue("payment/aditumboleto/expiration");
        $json_array['charge']['deadline'] = date("Y-m-d",$tTime);

        $json_array['charge']['customer']['name'] = $billingAddress->getName();
        $json_array['charge']['customer']['email'] = $quote->getCustomerEmail();
        $json_array['charge']['customer']['documentType'] = $this->getDocumentTypeId();

        $json_array['charge']['customer']['document'] = $quote->getCustomer()->getTaxvat();

        $json_array['charge']['customer']['phone']['countryCode'] = "55";
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $json_array['charge']['customer']['phone']['areaCode'] = substr($phone_number,0,2);
        $json_array['charge']['customer']['phone']['number'] = substr($phone_number,2);
        $json_array['charge']['customer']['phone']['type'] = 1;
        $json_array['charge']['customer']['address']['street'] = $billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")];
        $json_array['charge']['customer']['address']['number'] = $billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")];
        $json_array['charge']['customer']['address']['complement'] = $billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")];
        $json_array['charge']['customer']['address']['neighborhood'] = $billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")];
        $json_array['charge']['customer']['address']['city'] = $billingAddress->getCity();
        $json_array['charge']['customer']['address']['state'] =
            $this->codigoUF($billingAddress->getRegion());
        $json_array['charge']['customer']['address']['country'] = "BR";
        $json_array['charge']['customer']['address']['zipCode'] = $billingAddress->getPostcode();
        $transactions['amount'] = $order->getGrandTotal();
        $transactions['fine']['startDate'] = date("Y-m-d",$tTime);
        $transactions['fine']['amount'] = 0;
        $transactions['fine']['interest'] = 0;

        $transactions['discount']['type'] = 0;
        $transactions['discount']['amount'] = 0;
        $transactions['discount']['deadline'] = date("Y-m-d");
        $transactions['instructions'] = "Não aceitar pagamento após o vencimento";

        $json_array['transactions'][] = $transactions;

        $json_array['bankIssuerDaysToCancel'] = $this->_scopeConfig->getValue("payment/aditumboleto/expiration");;
        $json_array['source'] = 1; // Sim	Define a fonte de cobrança.. Enum.

        $transactions['card']['cardNumber'] = preg_replace('/[\-\s]+/', '', $info->getCcNumber());
        $transactions['card']['cvv'] = $info->getCcCid();
        $transactions['card']['brandName'] = "MasterCard";
        if($info->getFullName()) {
            $transactions['card']['cardholderName'] = $info->getFullname();
        }
        else{
            $transactions['card']['cardholderName'] = $order->getBillingAddress()->getFullName();
        }
        $transactions['card']['expirationMonth'] = $info->getCcExpMonth();
        $transactions['card']['expirationYear'] = $info->getCcExpYear();
        $grandTotal = $order->getGrandTotal() * 100;
        $transactions['paymentType'] = 2;
        $transactions['amount'] = (int)$grandTotal;
        $transactions['softDescriptor'] = $this->_storeManager->getStore()->getName() ." - ".$order->getIncrementId();
        $transactions['merchantTransactionId'] = $order->getIncrementId();

        $json_array['charge']['transactions'] = [$transactions];

        $json_input = json_encode($json_array);
        $this->logger->info(json_encode($json_array,JSON_PRETTY_PRINT));
        $result = $this->apiRequest($url,"POST",$json_input);
        $result_array = json_decode($result,true);
        return $result_array;
    }
    public function createOrderCc(\Magento\Sales\Model\Order\Interceptor $order, $info, $payment, $preAuth = 0)
    {
        if($this->enableExternalExtension){
            return $this->extCreateOrderCc($order,$info,$payment,$preAuth);
        }
        $url = $this->url . "/charge/authorization";
        $quote = $this->checkoutSession->getQuote();
//        $this->logger->info(json_encode($payment->getAdditionalInformation(),true));
        $billingAddress = $quote->getBillingAddress();

        $json_array['charge']['customer']['name'] = $order->getBillingAddress()->getName();
        $json_array['charge']['customer']['email'] = $quote->getCustomerEmail();

        $json['charge']['customer']['phone']['countryCode'] = "55";
        $phone_number = filter_var($billingAddress->getTelephone(), FILTER_SANITIZE_NUMBER_INT);
        $json['charge']['customer']['phone']['areaCode'] =  substr($phone_number,0,2);
        $json['charge']['customer']['phone']['number'] = substr($phone_number,2);
        $json['charge']['customer']['phone']['type'] = \AditumPayments\ApiSDK\Enum\PhoneType::MOBILE;
        $json['charge']['customer']['address']['street'] = $billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/street")];
        $json['charge']['customer']['address']['number'] = $billingAddress
            ->getStreet()[$this->scopeConfig->getValue("payment/aditum/number")];
        if(isset($billingAddress->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")])) {
            $json['charge']['customer']['address']['complement'] = $billingAddress
                ->getStreet()[$this->scopeConfig->getValue("payment/aditum/complement")];
        }
        else{
            $json['charge']['customer']['address']['complement'] = "";
        }
        if(isset($billingAddress->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")])) {
            $json['charge']['customer']['address']['neighborhood'] = $billingAddress
                ->getStreet()[$this->scopeConfig->getValue("payment/aditum/district")];
        }
        else{
            $json['charge']['customer']['address']['neighborhood'] = "";
        }
        $json['charge']['customer']['address']['city'] = $billingAddress->getCity();
        $json['charge']['customer']['address']['state'] = $this->codigoUF($billingAddress->getRegion());
        $json['charge']['customer']['address']['country'] = "BR";
        $json['charge']['customer']['address']['zipCode'] = $billingAddress->getPostcode();

        $transactions['card']['cardNumber'] = preg_replace('/[\-\s]+/', '', $info->getCcNumber());
        $transactions['card']['cvv'] = $payment->getAdditionalInformation('cc_cid');
        $transactions['card']['brandName'] = "MasterCard";
        $transactions['card']['cardholderName'] = $payment->getAdditionalInformation('fullname');
        $transactions['card']['expirationMonth'] = $payment->getAdditionalInformation('cc_exp_month');
        $transactions['card']['expirationYear'] = $payment->getAdditionalInformation('cc_exp_year');
        $grandTotal = $order->getGrandTotal() * 100;
        $transactions['paymentType'] = 2;
        $transactions['amount'] = $grandTotal;
        $transactions['softDescriptor'] = $this->_storeManager->getStore()->getName() ." - ".$order->getIncrementId();
        $transactions['merchantTransactionId'] = $order->getIncrementId();

        $json_array['charge']['transactions'] = [$transactions];

        $json_input = json_encode($json_array);
        $this->logger->info(json_encode($json_array,JSON_PRETTY_PRINT));
        $result = $this->apiRequest($url,"POST",$json_input);
        $result_array = json_decode($result,true);
        return $result_array;
    }
    public function extCreateOrderCc(\Magento\Sales\Model\Order\Interceptor $order,$info,$payment, $preAuth=0)
    {
        \AditumPayments\ApiSDK\Configuration::initialize();
        \AditumPayments\ApiSDK\Configuration::setUrl(\AditumPayments\ApiSDK\Configuration::DEV_URL);
        \AditumPayments\ApiSDK\Configuration::setCnpj($this->getClientId());
        \AditumPayments\ApiSDK\Configuration::setMerchantToken($this->getClientSecret());
        \AditumPayments\ApiSDK\Configuration::login();

        $gateway = new \AditumPayments\ApiSDK\Gateway;
        $authorization = new \AditumPayments\ApiSDK\Domains\Authorization;
        if($preAuth){
            unset($authorization);
            $authorization = new \AditumPayments\ApiSDK\Domains\PreAuthorization;
        }
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
        if($payment->getAdditionalInformation('cc_dc_choice')!="dc") {
            $authorization->transactions->setInstallmentNumber($payment->getAdditionalInformation('installments'));
        }
        $grandTotal = $order->getGrandTotal() * 100;
        $authorization->transactions->setAmount($grandTotal);

        $result = $gateway->charge($authorization);

        $this->logger->info("External Apitum API Return: ".json_encode($result));
        return $result;
    }
    public function apiRequest($url, $method = "GET", $json = "")
    {
        $this->logger->info("Aditum Request starting...");
        $_token = $this->getToken();
        if (!$_token) return false;
        $method = strtoupper($method);
        $this->logger->info("Aditum Request URL:" . $url);
        $this->logger->info("Aditum Request METHOD:" . $method);
        if ($json) {
            $this->logger->info("Aditum Request JSON:" . $json);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $_token));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        $this->logger->info("Aditum Request OUTPUT:" . $result);
        $this->logger->info("header: ".curl_getinfo($ch, CURLINFO_HTTP_CODE)." - ".$url." - ".$json);
        $this->logger->info($url." - ".$json." - ".$result);
        $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($http_code==500){
            return false;
        }
        return $result;
    }
    public function getToken()
    {
        $this->logger->info("ameRequest getToken starting...");
        if($this->enableExternalExtension){
//            return $this->extGetToken();
        }
        // Package independent below
        //////////////////////////////
        // check if existing token will be expired within 10 minutes
//        if($token = $this->dbAditum->getToken()){
//            return $token;
//        }
        $client_id = $this->getClientId();
        $client_secret = $this->getClientSecret();
        if (!$client_id || !$client_secret) {
            $this->logger->info("Aditum Request OUTPUT: user/pass not found on db");
            return false;
        }
        $client_secret = password_hash($client_id.$client_secret,PASSWORD_BCRYPT,['cost' => 12]);
//        $payload = [
//            'grant_type' => 'client_credentials',
//            'client_id' => $client_id,
//            'client_secret'   => $client_secret
//        ];
        $headers = array(
            "merchantCredential: ".$client_id,
            "Authorization: ".$client_secret,
            "Content-Length: 0"
        );

        $url = $this->url . "/merchant/auth";
        $this->logger->info("Get Token URL: ".$url);
        $this->logger->info("Get Token input: ".json_encode($headers));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch,CURLOPT_HEADER, false);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $result = curl_exec($ch);
        $this->logger->info("Get Token Result: ".$result);
        $result_array = json_decode($result,true);
        if(!array_key_exists("accessToken",$result_array))
            return $this->logError("getToken",$url,$result,"(json encoded) ".json_encode($headers));
        $expires_in = time() + 3600;// intval($result_array['expires_in']);
        $this->dbAditum->updateToken($expires_in,$result_array['accessToken']);
        return $result_array['accessToken'];
    }
//    public function extGetToken()
//    {
//        $config = \AditumPayments\ApiSDK\Config\Configuration::getInstance();
//        if($this->url=="https://payment-dev.aditum.com.br/v2"){
//            $config->setUrl($this->extAditumConfig::DEV_URL);
//        }
//        else{
//            $config->setUrl($this->extAditumConfig::PROD_URL);
//        }
//        $config->setCnpj($this->getClientId());
//        $config->setMerchantToken($this->getClientId());
//        $auth = new \AditumPayments\ApiSDK\Authentication;
//        $result = $auth->requestToken();
//        $this->logger->info("External Aditum GetToken result: ".json_encode($result));
//        if(!isset($result['token'])){
//            return false;
//        }
//        return $result['token'];
//    }
    public function logError($action,$url,$output,$input="")
    {
        $this->logger->error("ameRequest error: ".$action." - ".$url." - ".$input." - ".$output);
        return false;
    }
    public function getClientId()
    {
        return $this->scopeConfig->getValue('payment/aditum/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function getClientSecret(){
        return $this->scopeConfig->getValue('payment/aditum/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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
    public function getCpfCnpjNumber($taxvat)
    {

    }
}
