<?php

declare(strict_types=1);

namespace AditumPayment\Magento2\Api\Data;

interface PaymentAdditionalInformationInterface
{
    public const STATUS = 'status';
    public const CALLBACK_STATUS = 'callbackStatus';
    public const STATUS_AUTHORIZED = 'Authorized';
    public const STATUS_NOT_AUTHORIZED = 'NotAuthorized';
    public const STATUS_PRE_AUTHORIZED = 'PreAuthorized';
    public const ORDER_CREATED = 'order_created';
    public const CC_NUMBER = 'cc_number';
    public const CC_CID = 'cc_cid';
}
