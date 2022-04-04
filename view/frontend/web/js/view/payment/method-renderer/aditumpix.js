/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
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
                // this._super()
                //     .observe([
                //         'boletofullname',
                //         'boletodocument'
                //     ]);

                return this;
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        // 'boletofullname': jQuery('#'+this.getCode() + '_boletofullname').val(),
                        // 'boletodocument': jQuery('#'+this.getCode() + '_boletodocument').val(),
                        // 'antifraud_token': jQuery('#antifraud_token').val()
                    }
                };
            },
            getAntiFraudType: function() {
                return window.checkoutConfig.payment.aditumboleto.antifraud_type;
            },
            getAntiFraudId: function() {
                return window.checkoutConfig.payment.aditumboleto.antifraud_id;
            },
            getInstruction: function() {
                return window.checkoutConfig.payment.aditumboleto.instruction;
            },
            getDue: function() {
                return window.checkoutConfig.payment.aditumboleto.due;
            },
            getFullName: function() {
                return window.checkoutConfig.payment.aditumboleto.fullname;
            },
            getTaxVat: function() {
                return window.checkoutConfig.payment.aditumboleto.taxvat;
            },
            getTermsHtml: function () {
                return '<a target="_blank" href="' + this.getTermsUrl() +
                    '">' + this.getTermsTxt() + '</a>';
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
