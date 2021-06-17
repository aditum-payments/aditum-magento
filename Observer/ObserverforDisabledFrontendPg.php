<?php

namespace AditumPayment\Magento2\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ObjectManager;

class ObserverforDisabledFrontendPg implements ObserverInterface
{
    protected $_appState;
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\State $appState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->_appState = $appState;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $result = $observer->getEvent()->getResult();
        $method_instance = $observer->getEvent()->getMethodInstance();
        $quote = $observer->getEvent()->getQuote();
        if ($method_instance->getCode() == 'aditumcc'
            &&!$this->scopeConfig->getValue("payment/aditum_cc/enable")) {
            $result->setData('is_available', false);
        }
        if ($method_instance->getCode() == 'aditumboleto'
            &&!$this->scopeConfig->getValue("payment/aditum_boleto/enable")) {
            $result->setData('is_available', false);
        }
    }
    protected function getDisableAreas()
    {
        return array(\Magento\Framework\App\Area::AREA_FRONTEND, \Magento\Framework\App\Area::AREA_WEBAPI_REST);
    }
}
