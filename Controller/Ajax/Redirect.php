<?php
namespace Aditum\Payment\Controller\Ajax;

class Redirect extends \Magento\Framework\App\Action\Action
{
    protected $checkoutSession;
    protected $result;
    protected $api;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\ResultFactory $result,
        \Aditum\Payment\Helper\Api $api
     ) {
        $this->checkoutSession = $checkoutSession;
        $this->result = $result;
        $this->api = $api;
        parent::__construct($context);
    }
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $pixId = $order->getExtOrderId();
        $url = $this->api->getOrderRedirectUrl($pixId);
        $resultRedirect = $this->result->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($url);
        return $resultRedirect;
    }
}
