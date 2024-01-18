<?php
namespace AditumPayment\Magento2\Model;

class ConfigProvider
{
    protected $scopeConfig;
    protected $assetRepo;
    protected $storeManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
        $this->storeManager = $storeManager;
    }

    // Common functions
    public function getStaticUrl()
    {
        return $this->assetRepo->getUrl("AditumPayment_Magento2::images");
    }

    public function getTermsUrl()
    {
        $fileName = "Termos-de-Uso-Portal-Aditum-V3-20210512.pdf";
        return $this->assetRepo->getUrl("AditumPayment_Magento2::pdf/".$fileName);
    }
    public function getTermsTxt()
    {
        return "Ao proceder com essa compra eu aceito os ";
    }
    public function getAntiFraudType()
    {
        $type_id = $this->scopeConfig->getValue("payment/aditum/antifraudtype");
        if ($type_id==1) {
            return "clearsale";
        }
        if ($type_id==2) {
            return "konduto";
        }
        return false;
    }
    public function getAntiFraudId()
    {
        return $this->scopeConfig->getValue("payment/aditum/antifraud_id");
    }
}
