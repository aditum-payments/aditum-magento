define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'AditumPayment_Magento2/js/model/pix-validator'
    ],
    function (Component, additionalValidators, pixValidator) {
        'use strict';
        additionalValidators.registerValidator(pixValidator);
        return Component.extend({});
    }
);