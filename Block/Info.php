<?php
namespace Aditum\Payment\Block;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Tatix_PIX::info.phtml';
    protected $api;

    public function __construct(
        \Magento\Framework\Pricing\Helper\Data $currency,
        \Magento\Backend\Block\Template\Context $context,
        \Aditum\Payment\Helper\Api $api,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_currency = $currency;
        $this->api = $api;
    }
    public function getPixUrl()
    {
        $pixID = $this->getOrder()->getExtOrderID();
        return $this->api->getOrderRedirectUrl($pixID);
    }
    public function getOrder()
    {
        return $this->getInfo()->getOrder();
    }
}
