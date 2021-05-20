<?php
namespace AditumPayment\Magento2\Model;

class ConfigProvider
{
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }


    // Common functions

    public function getTermsUrl()
    {
        return "https://www.aditum.com.br/";
    }
    public function getTermsTxt()
    {
        return "Aceito os termos e condições";
    }
    public function getAntiFraudType()
    {
        $type_id = $this->scopeConfig->getValue("payment/aditum/antifraudtype");
        if($type_id==1){
            return "clearsale";
        }
        if($type_id==2){
            return "konduto";
        }
        return false;
    }
    public function getAntiFraudId()
    {
        return $this->scopeConfig->getValue("payment/aditum/antifraud_id");
    }
}
