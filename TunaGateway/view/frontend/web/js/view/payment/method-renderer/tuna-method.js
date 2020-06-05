define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
    ],
    function ($, quote, Component, setPaymentInformationAction, placeOrder) {
        'use strict';
        require(['jquery', 'jquery_mask'], function ($) {
            $("#tuna_credit_card_document").live("keydown", function () {
                try {
                    $("#tuna_credit_card_document").unmask();
                } catch (e) { }

                if ($("#tuna_credit_card_document").val().length < 11) {
                    $("#tuna_credit_card_document").mask("999.999.999-99");
                } else {
                    $("#tuna_credit_card_document").mask("99.999.999/9999-99");
                }

                var elem = this;
                setTimeout(function () {
                    elem.selectionStart = elem.selectionEnd = 10000;
                }, 0);
                var currentValue = $(this).val();
                $(this).val('');
                $(this).val(currentValue);
            });
            
            $("#tuna_credit_card_code").mask("9999");
            $("#tuna_credit_card_number").mask("9999 9999 9999 9999");

        });
        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/tuna'
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            getMonthsValues: function () {
                return _.map(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'], function (value, key) {
                    return {
                        'value': key + 1,
                        'month': value
                    };
                });
            },
            getYearsValues: function () {
                let thisYear = (new Date()).getFullYear();
                let maxYear = thisYear + 20;
                let years = [];
                for (let i = thisYear; i < maxYear; i++) {
                    years.push(i);
                }

                return _.map(years, function (value, key) {
                    return {
                        'value': value,
                        'year': value
                    };
                });
            },
            onlyNumbers: function (value) {
                return value.replace(/\D/g, '');
            },
            endOrder: function (self, tunaCardToken, paymentData, messageContainer) {
                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': {
                        'credit_card_document': $('#tuna_credit_card_document')[0].value,
                        'credit_card_hash': window.checkoutConfig.payment.tunagateway.tokenid,
                        'credit_card_token': tunaCardToken,
                        'credit_card_holder_name': $('#tuna_credit_card_holder')[0].value,
                    }
                })).done(function () {
                    console.log("lebaraaaaa");
                    $.when(placeOrder(paymentData, messageContainer)).done(function () {
                        console.log("ma ooeeeee");
                        $.mage.redirect(window.checkoutConfig);
                    });
                    //return;
                }).fail(function () {
                    console.log("iê iê");
                }).always(function () {
                    console.log("glu glu");
                    // fullScreenLoader.stopLoader();
                });
            },
            placeOrder: function () {
                let self = this;
                var paymentData = quote.paymentMethod();
                var messageContainer = this.messageContainer;
                let data = {
                    tunaSessionId: window.checkoutConfig.payment.tunagateway.tokenid,
                    cardHolder: $('#tuna_credit_card_holder')[0].value,
                    cardNumber: this.onlyNumbers($('#tuna_credit_card_number')[0].value),
                    creditCardDocument: this.onlyNumbers($('#tuna_credit_card_document')[0].value),
                    cvv: $('#tuna_credit_card_code')[0].value,
                    expirationMonth: $('#tuna_credit_card_expiration_month')[0].value,
                    expirationYear: $('#tuna_credit_card_expiration_year')[0].value,
                };
                $.post("https://5ec81c52155c130016a908a7.mockapi.io/Tuna/Tokenizer", data, function (returnedData) {
                    self.endOrder(self, returnedData.sessionID, paymentData, messageContainer);
                });
            }
        });
    }
);
