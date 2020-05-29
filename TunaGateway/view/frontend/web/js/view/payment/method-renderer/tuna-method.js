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
        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/tuna',
                tunaSessionId: window.checkoutConfig.payment.tunagateway.tokenid
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
            formatCreditCard: function () {
                let ccNumber = jQuery("#tuna_credit_card_number")[0].value.replace(/[^0-9 ]/g, "")
                jQuery("#tuna_credit_card_number")[0].value = this.ccFormater(ccNumber);
            },
            ccFormater: function (value) {
                var v = value.replace(/\s+/g, '').replace(/[^0-9]/gi, '')
                var matches = v.match(/\d{4,16}/g);
                var match = matches && matches[0] || ''
                var parts = []
                for (let i = 0, len = match.length; i < len; i += 4) {
                    parts.push(match.substring(i, i + 4))
                }
                if (parts.length) {
                    return parts.join(' ')
                } else {
                    return value
                }
            },
            onlyNumbers: function (value) {
                return value.replace(/\D/g, '');
            },
            getCookie: function (cname) {
                let name = cname + "=";
                let decodedCookie = decodeURIComponent(document.cookie);
                let ca = decodedCookie.split(';');
                for (let i = 0; i < ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) == ' ') {
                        c = c.substring(1);
                    }
                    if (c.indexOf(name) == 0) {
                        return c.substring(name.length, c.length);
                    }
                }
                return "";
            },
            endOrder: function (self, tunaCardToken, paymentData, messageContainer) {
                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': {
                        'credit_card_document': document.getElementById('creditCardDocument').value,
                        'credit_card_hash': document.getElementById('tunaSessionId').value,
                        'credit_card_token': tunaCardToken,
                        'credit_card_holder_name': document.getElementById('creditCardHolder').value,
                    }
                })).done(function () {
                    $.when(placeOrder(paymentData, messageContainer)).done(function () {
                        console.log("ma ooeeeee");
                        // $.mage.redirect(window.checkoutConfig.pagseguro_boleto);
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
                    tunaSessionId: document.getElementById('tunaSessionId').value,
                    cardHolder: document.getElementById('creditCardHolder').value,
                    cardNumber: this.onlyNumbers(document.getElementById('tuna_credit_card_number').value),
                    cvv: document.getElementById('creditCardCode').value,
                    expirationMonth: document.getElementById('creditCardExpirationMonth').value,
                    expirationYear: document.getElementById('creditCardExpirationYear').value,
                };
                $.post("https://5ec81c52155c130016a908a7.mockapi.io/Tuna/Tokenizer", data, function (returnedData) {
                    self.endOrder(self,returnedData, paymentData, messageContainer);
                });
            }
        });
    }
);
