<?php

namespace AditumPayment\Magento2\Block;

class PixSuccess extends \Magento\Checkout\Block\Onepage\Success
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
    }

    /**
     * @return float|null
     */
    public function getPrice()
    {
        return $this->getOrder()->getGrandTotal();
    }


    /**
     * @return false|\Magento\Sales\Model\Order
     */
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

    /**
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return $this->getOrder()->getPayment()->getMethod();
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQrCodeUrl(): string
    {
        $mediaUrl = $this->_storeManager->getStore()
                ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $incrementId = $this->getOrder()->getIncrementId();
        return $mediaUrl . "/aditumpix/" . $incrementId . ".png";
    }
}
