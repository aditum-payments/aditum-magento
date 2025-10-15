<?php

namespace AditumPayment\Magento2\Logger;

use Monolog\Logger;

class ApiHandler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/aditum_api.log';
}