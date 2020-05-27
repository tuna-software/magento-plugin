define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, quote, Component) {
        'use strict';
        quote.paymentMethod.subscribe(function (method) { console.log(method); }, null, 'change');
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
            placeOrder: function () {
                //let sessionID = this.getCookie("tuna_sessionid");
                //var paymentData = quote.paymentMethod();
                let data = {
                    cardHolder: document.getElementById('creditCardHolder').value,
                    cardNumber: document.getElementById('tuna_credit_card_number').value,
                    cvv: document.getElementById('creditCardCode').value,
                    expirationMonth: document.getElementById('creditCardExpirationMonth').value,
                    expirationYear: document.getElementById('creditCardExpirationYear').value,
                };
                $.post("https://5ec81c52155c130016a908a7.mockapi.io/Tuna/Tokenizer", data, function (returnedData) {
                    console.log(returnedData);
                });
            }
        });
    }
);
