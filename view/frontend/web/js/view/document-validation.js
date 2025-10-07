define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'AditumPayment_Magento2/js/model/document-validator'
    ],
    function (Component, additionalValidators, documentValidator) {
        'use strict';
        additionalValidators.registerValidator(documentValidator);
        return Component.extend({});
    }
);