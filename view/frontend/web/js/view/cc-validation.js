define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'AditumPayment_Magento2/js/model/cc-validator'
    ],
    function (Component, additionalValidators, ccValidator) {
        'use strict';
        additionalValidators.registerValidator(ccValidator);
        return Component.extend({});
    }
);