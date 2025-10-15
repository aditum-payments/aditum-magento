<?php

namespace AditumPayment\Magento2\Logger;

use Monolog\Logger;

class ApiLogger extends \Magento\Framework\Logger\Monolog
{
    /**
     * @param string $name
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        $name,
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct($name, $handlers, $processors);
    }
}