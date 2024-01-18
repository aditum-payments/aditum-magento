/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'AditumPayment_Magento2/payment/pix'
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'pixfullname',
                        'pixdocument'
                    ]);

                return this;
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'pixfullname': jQuery('#'+this.getCode() + '_pixfullname').val(),
                        'pixdocument': jQuery('#'+this.getCode() + '_pixdocument').val()
                    }
                };
            },
            getAntiFraudType: function () {
                return window.checkoutConfig.payment.aditumpix.antifraud_type;
            },
            getAntiFraudId: function () {
                return window.checkoutConfig.payment.aditumpix.antifraud_id;
            },
            getFullName: function () {
                return window.checkoutConfig.payment.aditumpix.fullname;
            },
            getTaxVat: function () {
                return window.checkoutConfig.payment.aditumpix.taxvat;
            },
            getTermsHtml: function () {
                return '<p> ' + this.getTermsTxt() + '<a target="_blank" href="' + this.getTermsUrl() +
                    '">termos e condições</a></p>';
            },
            getTermsUrl: function () {
                return window.checkoutConfig.payment.aditumcc.terms_url;
            },
            getTermsTxt: function () {
                return window.checkoutConfig.payment.aditumcc.terms_txt;
            },
        });
    }
);
