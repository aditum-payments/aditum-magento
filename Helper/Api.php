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

    public function __construct(\Magento\Framework\App\Helper\Context $context,
                                \Magento\Framework\HTTP\Client\Curl $curl,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Psr\Log\LoggerInterface $logger,
                                \Aditum\Payment\Helper\DbPIX $dbAditum,
                                \Magento\Store\Model\StoreManagerInterface $storeManager
                                )
    {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->dbAditum = $dbAditum;
        $this->_storeManager = $storeManager;
        $env = $this->scopeConfig->getValue('payment/aditum/environment', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($env == 0) {
            $this->url = "https://payment-dev.aditum.com.br/v2";
        }
        else if ($env == 1) {
            $this->url = "https://payment.aditum.com.br/v2";
        }
    }
    public function createOrderCc($order)
    {
        $url = $this->url . "/orders";

        $json_array['charge']['customer']['name'] = $order->getCustomer()->getName();
        $json_array['charge']['customer']['email'] = $order->getCustomer()->getEmail();

        $json_array['charge']['paymentType'] = 2;
        $grandTotal = $order->getGrandTotal() * 100
        $json_array['charge']['amount'] = (int)$grandTotal;
        $json_array['charge']['softDescriptor'] = $order->getIncrementId();
        $json_array['charge']['merchantTransactionId'] = $order->getIncrementId();


        $json_input = json_encode($json_array);
        $this->logger->info(json_encode($json_array,JSON_PRETTY_PRINT));
        $result = $this->apiRequest($url,"POST",$json_input);
        $result_array = json_decode($result,true);
        if(!isset($result_array['id'])) return false;
        return $result_array['id'];
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
        curl_close($ch);
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
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret'   => $client_secret
        ];
        $headers = array(
            "merchantCredential: ".$client_id,
            "Authorization: ".$client_secret
        );

        $url = $this->url . "/merchant/auth";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_HEADER, $headers);
//        curl_setopt($ch,CURLOPT_HEADER, false);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $result = curl_exec($ch);
        $this->logger->info("Get Token Result: ".$result);
        $result_array = json_decode($result,true);
        if(!array_key_exists("access_token",$result_array))
            return $this->logError("getToken",$url,$result,"(json encoded) ".json_encode($payload));
        $expires_in = (int)time() + intval($result_array['expires_in']);
        $this->dbAditum->updateToken($expires_in,$result_array['access_token']);
        return $result_array['access_token'];
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
}
