<?php
namespace Aditum\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;


class ConfigProviderBoleto implements ConfigProviderInterface
{

   /**
     * @var string[]
     */
    protected $methodCode = "aditumboleto";

    /**
     * @var Checkmo
     */
    protected $method;

    /**
     * @var Escaper
     */
    protected $escaper;

    protected $scopeConfig;

    protected $customer;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Session $customer
    )
    {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->scopeConfig = $scopeConfig;
        $this->customer = $customer;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                'aditumboleto' => [
                    'instruction' =>  $this->getInstruction(),
                    'due' => $this->getDue(),
                    'fullname' => $this->getFullName(),
                    'taxvat' => $this->getTaxVat()
                ],
            ],
        ] : [];
    }

    /**
     * Get instruction from config
     *
     * @return string
     */
    protected function getInstruction()
    {
        return nl2br($this->escaper->escapeHtml($this->scopeConfig->getValue("payment/aditum_boleto/instruction")));
    }


    /**
     * Get due from config
     *
     * @return string
     */
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
