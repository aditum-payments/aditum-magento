define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
    ],
    function (Component, url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tatix_PIX/payment/pix'
            },
            initObservable: function () {
                this._super();
                return this;
            },
            afterPlaceOrder: function () {
                window.location.replace(url.build('pixmethod/ajax/redirect'));
            },
            getLogoImagePath: function () {
                return require.toUrl('Tatix_PIX/images/logo_pix.png');
            },
            getCode: function() {
                return 'pix';
            },
            isActive: function() {
                return true;
            },
            isRadioButtonVisible: function() {
                return true;
            },
            /** Returns send check to info */
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
        });
    }
);
