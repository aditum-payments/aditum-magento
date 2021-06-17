<?php
namespace AditumPayment\Magento2\Block\Form;

class CreditCard extends \Magento\Payment\Block\Form\Cc
{
    /**
     * @var string
     */
    protected $_template = 'AditumPayment_Magento2::form/cc.phtml';
}
