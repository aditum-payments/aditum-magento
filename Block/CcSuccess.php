<?php

namespace AditumPayment\Magento2\Block;

class CcSuccess extends \Magento\Checkout\Block\Onepage\Success
{
    protected $checkoutSession;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        array $data = [])
    {
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
    }
    public function getOrder()
    {
        if ($this->checkoutSession->getLastRealOrderId()) {
            return $this->checkoutSession->getLastRealOrder();
        }
        if ($order = $this->getInfo()->getOrder()) {
            return $order;
        }
        return false;
    }
    public function getPaymentMethod()
    {
        return $this->getOrder()->getPayment()->getMethod();
    }
}
