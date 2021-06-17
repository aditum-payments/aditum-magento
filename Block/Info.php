<?php
namespace AditumPayment\Magento2\Block;

class Info extends \Magento\Payment\Block\Info
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }
    public function getOrder()
    {
        return $this->getInfo()->getOrder();
    }
}
