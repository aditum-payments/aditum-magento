<?php

namespace AditumPayment\Magento2\Block;

use Magento\Framework\View\Element\Template;

class AntiFraud extends \Magento\Framework\View\Element\Template
{
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Template\Context $context, array $data = [])
    {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function getAntiFraudJs()
    {
        if(!$this->getAntiFraudType()){
            return "";
        }
        return "<script src='".$this->getAntiFraudUrl()."'/>";
//        if($this->request->getFullActionName()=="checkout_index_index"){
//            if($this->getAntiFraudType()==1){
//                return "<script src=''/>";
//            }
//            if($this->getAntiFraudType()==2){
//                return "<script src=''/>";
//            }
//        }
//        return '';
    }
    public function getExtraJs(){
        if(!$this->getAntiFraudType()){
            return "";
        }
        return '<script type="text/javascript" src="'.$this->getAntiFraudUrl().'"></script>';
//            if($this->getAntiFraudType()){
//                return "<script src=''/>";
//            }
//            if($this->_scopeConfig->getValue('payment/aditum/antifraudtype',
//                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE)==2){
//                return "<script src=''/>";
//            }
        return '';
    }
    public function getAntiFraudUrl()
    {
        return $this->getViewFileUrl('AditumPayment_Magento2::js/antifraud.js');
    }
    public function getAntiFraudType()
    {
        return $this->_scopeConfig->getValue('payment/aditum/antifraudtype',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
