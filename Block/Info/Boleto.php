<?php

namespace AditumPayment\Magento2\Block\Info;

class Boleto extends \Magento\Payment\Block\Info
{
    protected $_template = 'AditumPayment_Magento2::info/boleto.phtml';

    public function getLinkPay(){
        $_info = $this->getInfo();
        $transactionId = $_info->getAdditionalInformation('boleto_url');

        return $transactionId;
    }

    public function getLinkPrintPay(){
        $_info = $this->getInfo();
        $transactionId = $_info->getAdditionalInformation('boleto_url');

        return $transactionId;
    }

    public function getLineCodeBoleto(){
        $_info = $this->getInfo();
        $transactionId = $_info->getAdditionalInformation('barcode');

        return $transactionId;
    }

    public function getExpirationDateBoleto(){
        $_info = $this->getInfo();
        $transactionId = $_info->getAdditionalInformation('expiration_date_boleto');

        return $transactionId;
    }
}
