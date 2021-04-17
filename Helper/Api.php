<?php

namespace Aditum\Payment\Helper;

class Api
{
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
                                \Aditum\Payment\Helper\DbAditum $dbAditum,
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

        $json_array['charge']['deadline'] =
            (int)$this->_scopeConfig->getValue("payment/moipboleto/expiration");// FAZER DATA * * * *  *

        $json_array['charge']['customer']['name'] = $order->getBillingAddress()->getFullName();
        $json_array['charge']['customer']['email'] = $quote->getCustomerEmail();
        $json_array['charge']['customer']['documentType'] = $this->getDocumentTypeId();
        $json_array['charge']['customer']['document'] = $quote->getCustomer()->getTaxvat();
        $json_array['charge']['customer']['phone']['countryCode'] = "55";
        $json_array['charge']['customer']['phone']['areaCode'] = "areaCode";
        $json_array['charge']['customer']['phone']['number'] = "areaCode";
        $json_array['charge']['customer']['phone']['type'] = 1;

        $json_array['charge']['customer']['address']['street'] = 1;
        $json_array['charge']['customer']['address']['number'] = 1;
        $json_array['charge']['customer']['address']['complement'] = 1;
        $json_array['charge']['customer']['address']['neighborhood'] = 1;

        $json_array['charge']['customer']['address']['city'] = 1;

        $json_array['charge']['customer']['address']['state'] = 1;

        $json_array['charge']['customer']['address']['country'] = 1;

        $json_array['charge']['customer']['address']['zipCode'] = 1;

        $transactions['amount'] = $order->getGrandTotal();

        $transactions['fine']['startDate'] = "";
        $transactions['fine']['amount'] = 1;
        $transactions['fine']['interest'] = 1; // int	Sim	Os juros financeiros representam um valor em porcentagem que será calculado todos os dias após a data de vencimento.

        $transactions['discount']['type'] = 1; // int	Sim	Tipo de desconto. Enum.



//        transactions.fine.startDate	string	Sim	Data de início que multa começará a contar.
//    transactions.fine.amount	int	Sim	Valor a ser pago após a data de vencimento.
//    transactions.fine.interest	int	Sim	Os juros financeiros representam um valor em porcentagem que será calculado todos os dias após a data de vencimento.
//    transactions.discount.type	int	Sim	Tipo de desconto. Enum.
//    transactions.discount.amount	int	Sim	Valor em centavos ou porcentagem com base no tipo de desconto.
//    transactions.discount.deadline	string	Sim	Data limite em que o desconto pode ser aplicado.
//    transactions.instructions	string	Não	Instruções de 255 caracteres a serem adicionadas no boleto bancário.
//    bankIssuerDaysToCancel	int	Não	Dias para cancelamento automático após o vencimento pelo emissor do boleto.
//    source	int	Sim	Define a fonte de cobrança.. Enum.
//    origin	string	Não	Uma propriedade que o cliente pode usar para identificar a origem da cobrança, como nome do fornecedor ou identificador.
//    receivers.id	string	Sim	O ID do recebedor.
//    receivers.mdrDiscount	bool	Não	IDs dos receptores envolvidos na cobrança. Isso definirá quem recebe a transação, dividindo o valor total da cobrança em pagamentos separados.
//    receivers.fixedAmountComission	int	Não	Quantia fixa que o recebedor deve receber.
//    receivers.chargeRemainder	bool	Não	Após o processamento dos valores de cada divisão, se sobrar centavos da divisão, este sinalizador indica o destinatário que deve receber os centavos extras. Isso pode ser usado para penny split (exemplo 100/3) ou para quando o destinatário não tem uma regra específica.
//    receivers.percentageComission	int	Não	Essa comissão é calculada sobre o valor da transação, semelhante a Aditum.EcommerceGateway.DataContract.V1.Charge.ReceiverChargeInfo.FixedAmountComission, que é uma porcentagem.





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
        $transactions['softDescriptor'] = $order->getIncrementId();
        $transactions['merchantTransactionId'] = $order->getIncrementId();

        $json_array['charge']['transactions'] = [$transactions];


        $json_input = json_encode($json_array);
        $this->logger->info(json_encode($json_array,JSON_PRETTY_PRINT));
        $result = $this->apiRequest($url,"POST",$json_input);
        $result_array = json_decode($result,true);
        return $result_array;
    }
    public function createOrderCc(\Magento\Sales\Model\Order\Interceptor $order,$info)
    {
        $url = $this->url . "/charge/authorization";
        $quote = $this->checkoutSession->getQuote();

        $json_array['charge']['customer']['name'] = $order->getBillingAddress()->getFullName();
        $json_array['charge']['customer']['email'] = $quote->getCustomerEmail();


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
        $transactions['softDescriptor'] = $order->getIncrementId();
        $transactions['merchantTransactionId'] = $order->getIncrementId();

        $json_array['charge']['transactions'] = [$transactions];


        $json_input = json_encode($json_array);
        $this->logger->info(json_encode($json_array,JSON_PRETTY_PRINT));
        $result = $this->apiRequest($url,"POST",$json_input);
        $result_array = json_decode($result,true);
        return $result_array;
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
        // check if existing token will be expired within 10 minutes
        if($token = $this->dbAditum->getToken()){
            return $token;
        }
        $client_id = $this->getClientId();
        $client_secret = $this->getClientSecret();
        if (!$client_id || !$client_secret) {
            $this->logger->info("Aditum Request OUTPUT: user/pass not found on db");
            return false;
        }
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
            return $this->logError("getToken",$url,$result,"(json encoded) ".json_encode($payload));
        $expires_in = time() + 3600;// intval($result_array['expires_in']);
        $this->dbAditum->updateToken($expires_in,$result_array['accessToken']);
        return $result_array['accessToken'];
    }
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
}
