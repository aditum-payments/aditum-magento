<?php
namespace Aditum\Payment\Block\Form;

class CreditCard extends \Magento\Payment\Block\Form\Cc
{
    /**
     * @var string
     */
    protected $_template = 'Aditum_Payment::form/cc.phtml';
}
