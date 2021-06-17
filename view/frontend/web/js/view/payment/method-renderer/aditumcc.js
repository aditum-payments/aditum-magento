/*browser:true*/
/*global define*/
define(
[
    'underscore',
	'jquery',
	'ko',
	'Magento_Checkout/js/model/quote',
	'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/checkout-data',
	'Magento_Payment/js/model/credit-card-validation/credit-card-data',
	'Magento_Payment/js/model/credit-card-validation/validator',
    'Magento_Checkout/js/model/payment/additional-validators',
	'AditumPayment_Magento2/js/model/credit-card-validation/credit-card-number-validator',
	'AditumPayment_Magento2/js/model/credit-card-validation/custom',
    'mage/url',
	'mage/calendar',
	'mage/translate',
    'domReady!'
],
function (
    _,
	$,
	ko,
	quote,
	priceUtils,
    Component,
    placeOrderAction,
    selectPaymentMethodAction,
    customer,
    checkoutData,
	creditCardData,
	validator,
    additionalValidators,
	cardNumberValidator,
	custom,
    url,
	calendar) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'AditumPayment_Magento2/payment/cc',
			    creditCardType: '',
                creditCardExpYear: '',
                creditCardExpMonth: '',
                creditCardNumber: '',
                creditCardSsStartMonth: '',
                creditCardSsStartYear: '',
                creditCardSsIssue: '',
                creditCardVerificationNumber: '',
                selectedCardType: null,
                selectCcDc: null
            },

			getCode: function() {
                return 'aditumcc';
            },

			initObservable: function () {
                this._super()
                    .observe([
                        'creditCardType',
                        'creditCardExpYear',
                        'creditCardExpMonth',
                        'creditCardNumber',
                        'creditCardVerificationNumber',
                        'creditCardSsStartMonth',
                        'creditCardSsStartYear',
                        'creditCardSsIssue',
                        'selectedCardType'
                    ]);
                this._super()
                    .observe([
                        'fullname',
                        'cc_cvv',
                        'cc_cid',
                        'cc_exp_month',
                        'cc_exp_year'
                    ]);

                return this;
            },

			 initialize: function () {
				this._super();

			  var self = this;
			  //Set credit card number to credit card data object
                this.creditCardNumber.subscribe(function (value) {
                    var result;

                    self.selectedCardType(null);

                    if (value === '' || value === null) {
                        return false;
                    }
                    result = cardNumberValidator(value);

                    if (!result.isPotentiallyValid && !result.isValid) {
                        return false;
                    }

                    if (result.card !== null) {
                        self.selectedCardType(result.card.type);
                        creditCardData.creditCard = result.card;
                    }

                    if (result.isValid) {
                        creditCardData.creditCardNumber = value;
                        self.creditCardType(result.card.type);
                    }
                });

				 //Set expiration year to credit card data object
                this.creditCardExpYear.subscribe(function (value) {
                    creditCardData.expirationYear = value;
                });

                //Set expiration month to credit card data object
                this.creditCardExpMonth.subscribe(function (value) {
                    creditCardData.expirationMonth = value;
                });

                //Set cvv code to credit card data object
                this.creditCardVerificationNumber.subscribe(function (value) {
                    creditCardData.cvvCode = value;
                });
                 $('#aditum_cc_number').attr("maxlength", 16);
                // document.onload = function (){
                //      document.getElementById('aditum_cc_number').onkeyup = function () {
                //      }
                //  };
             },
            getAntiFraudType: function() {
                return window.checkoutConfig.payment.aditumcc.antifraud_type;
            },
            getAntiFraudId: function() {
                return window.checkoutConfig.payment.aditumcc.antifraud_id;
            },
			getCvvImageUrl: function () {
	            return window.checkoutConfig.payment.aditumcc.image_cvv;
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
            getCvvImageHtml: function () {
	            return '<img src="' + this.getCvvImageUrl() +
	                '" alt="Referencia visual do CVV" title="Referencia visual do CVV" />';
	        },
			getCcAvailableTypes: function() {
                return window.checkoutConfig.payment.this.item.method.ccavailableTypes;
            },

            selectPaymentMethod: function() {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },
            getPublickey: function() {

                return window.checkoutConfig.payment.aditumcc.publickey;
            },
            getSingleIcon: function () {
                return window.checkoutConfig.payment.aditumcc.singleicon;
            },
			getIcons: function (type) {
                return window.checkoutConfig.payment.aditumcc.icons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.aditumcc.icons[type]
                    : false;
            },
			getCcAvailableTypesValues: function () {

                return _.map(window.checkoutConfig.payment.aditumcc.ccavailabletypes, function (value, key) {
                    return {
                        'value': key,
                        'type': value
                    };
                });
            },
			getCcYearsValues: function () {
                return _.map(window.checkoutConfig.payment.aditumcc.years, function (value, key) {
                    return {
                        'value': key,
                        'year': value
                    };
                });
            },
			getCcMonthsValues: function () {
                return _.map(window.checkoutConfig.payment.aditumcc.months, function (value, key) {
                    return {
                        'value': key,
                        'month': value
                    };
                });
            },
            getUseDocument: function(){
                return true;
            },
            getDocument: function(){
                return window.checkoutConfig.payment.aditumcc.document;
            },
            isActive :function(){
				return true;
			},

			getInstallmentsActive: ko.computed(function () {
			   return 1;
            }),

			getInstall: function () {
				var valor = quote.totals().base_grand_total;
				var type_interest 	= window.checkoutConfig.payment.aditumcc.type_interest;
				var info_interest 	= window.checkoutConfig.payment.aditumcc.info_interest;
				var min_installment = window.checkoutConfig.payment.aditumcc.min_installment;
				var max_installment = window.checkoutConfig.payment.aditumcc.max_installment;

				var json_parcelas = {};
				var count = 0;
			    json_parcelas[1] =
							{'parcela' : priceUtils.formatPrice(valor, quote.getPriceFormat()),
                             'total_parcelado' : priceUtils.formatPrice(valor, quote.getPriceFormat()),
                             'total_juros' :  0,
                             'juros' : 0
							};

				var max_div = (valor/min_installment);
					max_div = parseInt(max_div);

				if(max_div > max_installment) {
					max_div = max_installment;
				}else{
					if(max_div > 12) {
						max_div = 12;
					}
				}
				var limite = max_div;

				_.each( info_interest, function( key, value ) {
					if(count <= max_div){
                        info_interest[value] = 0;
						value = info_interest[value];
						if(value > 0){

							var taxa = value/100;
							if(type_interest == "compound"){
								var pw = Math.pow((1 / (1 + taxa)), count);
								var parcela = ((valor * taxa) / (1 - pw));
							} else {
								var parcela = ((valor*taxa)+valor) / count;
							}

							var total_parcelado = parcela*count;

							var juros = value;
							if(parcela > 5 && parcela > min_installment){
								json_parcelas[count] = {
									'parcela' : priceUtils.formatPrice(parcela, quote.getPriceFormat()),
									'total_parcelado': priceUtils.formatPrice(total_parcelado, quote.getPriceFormat()),
									'total_juros' : priceUtils.formatPrice(total_parcelado - valor, quote.getPriceFormat()),
									'juros' : juros,
								};
							}
						} else {
							if(valor > 0 && count > 0){
								json_parcelas[count] = {
										'parcela' : priceUtils.formatPrice((valor/count), quote.getPriceFormat()),
										'total_parcelado': priceUtils.formatPrice(valor, quote.getPriceFormat()),
										'total_juros' :  0,
										'juros' : 0,
									};
							}
						}
					}
					count++;
				});

				_.each( json_parcelas, function( key, value ) {
					if(key > limite){
						delete json_parcelas[key];
					}
				});
				return json_parcelas;
            },
			getInstallments: function () {
			var temp = _.map(this.getInstall(), function (value, key) {
				if(value['juros'] == 0){
					var info_interest = "sem juros";
				} else {
					var info_interest = "";
				}
				var inst = key+' x '+ value['parcela']+ ' ' + info_interest;
                    return {
                        'value': key,
                        'installments': inst
                    };

                });
			var newArray = [];
			for (var i = 0; i < temp.length; i++) {

				if (temp[i].installments!='undefined' && temp[i].installments!=undefined) {
					newArray.push(temp[i]);
				}
			}

			return newArray;
			},
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'cc_number': this.creditCardNumber(),
                        'cc_type': this.creditCardType(),
                        'cc_exp_month': jQuery('#'+this.getCode()+'_expiration').val(),
                        'cc_exp_year': jQuery('#'+this.getCode()+'_expiration_yr').val(),
                        'cc_cid': jQuery('#'+this.getCode()+'_cc_cid').val(),
                        'fullname': jQuery('#'+this.getCode()+'_fullname').val(),
                        'document': jQuery('#'+this.getCode()+'_document').val(),
                        'installments': jQuery('#'+this.getCode()+'_installments').val(),
                        'cc_dc_choice': 'cc',
                        'antifraud_token': jQuery('#antifraud_token').val()
                    }
                };
//                'cc_dc_choice': window.checkoutConfig.payment.aditumcc.cc_dc_choice,
            },
            getFullname: function() {
                return _.map(window.checkoutConfig.payment.aditumcc.fullname, function(value, key) {
                    return {
                        'value': key,
                        'fullname': value
                    }
                });
            },
            click: function(data, event) {
                this.change(event.target.value);
                return true;
            },
            getStaticUrl: function () {
                return window.checkoutConfig.payment.aditumcc.static_url;
            },
            getCardFlagByNumber: function (ccnumber = '') {
                var cardnumber = ccnumber.replace(/[^0-9]+/g, '');
//                    Aura: /^((?!504175))^((?!5067))(^50[0-9])/,
//                    BaneseCard: /^636117/,
//                    FortBrasil: /^628167/,
//                    GrandCard: /^605032/,
//                    PersonalCard: /^636085/,
//                    Valecard: /^606444|^606458|^606482,
                var cards = {
                    amex: /^3[47][0-9]{13}$/,
                    cabal: /(60420[1-9]|6042[1-9][0-9]|6043[0-9]{2}|604400)/,
                    dinerclub: /(36[0-8][0-9]{3}|369[0-8][0-9]{2}|3699[0-8][0-9]|36999[0-9])/,
                    discover: /^6(?:011|5[0-9]{2})[0-9]{12}/,
                    elo: /^4011(78|79)|^43(1274|8935)|^45(1416|7393|763(1|2))|^50(4175|6699|67[0-6][0-9]|677[0-8]|9[0-8][0-9]{2}|99[0-8][0-9]|999[0-9])|^627780|^63(6297|6368|6369)|^65(0(0(3([1-3]|[5-9])|4([0-9])|5[0-1])|4(0[5-9]|[1-3][0-9]|8[5-9]|9[0-9])|5([0-2][0-9]|3[0-8]|4[1-9]|[5-8][0-9]|9[0-8])|7(0[0-9]|1[0-8]|2[0-7])|9(0[1-9]|[1-6][0-9]|7[0-8]))|16(5[2-9]|[6-7][0-9])|50(0[0-9]|1[0-9]|2[1-9]|[3-4][0-9]|5[0-8]))/,
                    hipercard: /^606282|^3841(?:[0|4|6]{1})0/,
                    JCB: /^(?:2131|1800|35\d{3})\d{11}/,
                    mastercard: /^((5(([1-2]|[4-5])[0-9]{8}|0((1|6)([0-9]{7}))|3(0(4((0|[2-9])[0-9]{5})|([0-3]|[5-9])[0-9]{6})|[1-9][0-9]{7})))|((508116)\\d{4,10})|((502121)\\d{4,10})|((589916)\\d{4,10})|(2[0-9]{15})|(67[0-9]{14})|(506387)\\d{4,10})/,
                    sorocred: /^627892|^636414/,
                    visa: /^4[0-9]{15}$/
                };
                for (var flag in cards) {
                    if (cards[flag].test(cardnumber)) {
                        return flag;
                    }
                }
                return '';
            },
            getCcBrand: function () {
                var getCardFlagByNumber = this.getCardFlagByNumber;
                var getStaticUrl = this.getStaticUrl;

                var aditumCcNumber = $('#aditum_cc_number');
                if (aditumCcNumber.val().length < 6) {
                    return;
                }
                function handleKeyEvent()
                {
                    setTimeout(function () {


                        var ccFlag = getCardFlagByNumber(aditumCcNumber.val());
                        if(ccFlag == ''){
                            document.getElementById('aditum_cc_number').setAttribute('style', 'width:300px;max-width:300px;background-image: none !important');
                            return true;
                        }
                        var staticUrl = getStaticUrl() + '/aditumccbrands/';
                        var imageUrl = staticUrl + ccFlag + '.svg';
                        document.getElementById('aditum_cc_number').setAttribute('style', 'width:300px;max-width:300px;background-image: url(' + imageUrl + ') !important');
//                        aditumCcNumber.css('background-image', imageUrl);

                        // $.ajax({
                        //    url: '/rest/V1/aditum/brand/' + $('#aditum_cc_number').val(),
                        // }).done(function(response){
                        //     console.log(response.brand);
                        //     var staticUrl = this.getStaticUrl() + '/Aditum_Magento2/';
                        //     console.log(imageUrl);
                        //     $('#aditum_cc_number').css('background-image',imageUrl);
                        // });
                    }, 0);
                    return true;
                }
                aditumCcNumber.on('keyup',handleKeyEvent);
            },
            change: function (value) {
                if(value === 'dc') {
                    window.checkoutConfig.payment.aditumcc.cc_dc_choice = "dc";
                    $('#div-installments').hide();
                }
                else if(value === 'cc') {
                    window.checkoutConfig.payment.aditumcc.cc_dc_choice = "cc";
                    $('#div-installments').show();
                }
            },
			validate: function() {
				var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
