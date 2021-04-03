<?php
namespace Aditum\Payment\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;

class Email extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $inlineTranslation;
    protected $escaper;
    protected $transportBuilder;
    protected $logger;
    protected $_scopeConfig;

    public function __construct(
        Context $context,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder
    ) {
        parent::__construct($context);
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->transportBuilder = $transportBuilder;
        $this->logger = $context->getLogger();
        $this->_scopeConfig = $scopeConfig;
    }

    public function sendEmail($order)
    {
        try {
            $this->inlineTranslation->suspend();
            $sender = [
                'name' => $this->escaper->escapeHtml($this->_scopeConfig->getValue(
                    'trans_email/ident_general/name',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    null
                )),
                'email' => $this->escaper->escapeHtml($this->_scopeConfig->getValue(
                    'trans_email/ident_general/email',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    null
                ))
            ];


            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->_scopeConfig->getValue('email/demo/template'))
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars([
                    'order'  => $order,
                ])
                ->setFrom($sender)
                ->addTo($order->getCustomerEmail())
                ->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
        }
    }
}
