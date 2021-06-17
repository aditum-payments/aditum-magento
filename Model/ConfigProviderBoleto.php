<?php
namespace AditumPayment\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProviderBoleto extends \AditumPayment\Magento2\Model\ConfigProvider implements ConfigProviderInterface
{
    protected $methodCode = "aditumboleto";

    protected $method;
    protected $escaper;
    protected $scopeConfig;
    protected $customer;

    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Session $customer,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->scopeConfig = $scopeConfig;
        $this->customer = $customer;
        parent::__construct($scopeConfig,$assetRepo,$storeManager);
    }

    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                'aditumboleto' => [
                    'instruction' =>  $this->getInstruction(),
                    'due' => $this->getDue(),
                    'fullname' => $this->getFullName(),
                    'taxvat' => $this->getTaxVat(),
                    'terms_url' => $this->getTermsUrl(),
                    'terms_txt' => $this->getTermsTxt(),
                    'antifraud_type' => $this->getAntiFraudType(),
                    'antifraud_id' => $this->getAntiFraudId()
        ],
            ],
        ] : [];
    }

    protected function getInstruction()
    {
        return nl2br($this->escaper->escapeHtml($this->scopeConfig->getValue("payment/aditum_boleto/instruction")));
    }

    protected function getDue()
    {
        $day = (int)$this->scopeConfig->getValue("payment/aditum_boleto/expiration_days");
        if($day > 1) {
            return nl2br(sprintf(__('Expiration in %s days'), $day));
        } else {
            return nl2br(sprintf(__('Expiration in %s day'), $day));
        }

    }
    public function getFullName()
    {
        if($this->customer->isLoggedIn()) {
            return $this->customer->getCustomer()->getName();
        }
        else{
            return "";
        }
    }
    public function getTaxVat()
    {
        if($this->customer->isLoggedIn()) {
            return $this->customer->getCustomer()->getTaxvat();
        }
        else{
            return "";
        }
    }
}
