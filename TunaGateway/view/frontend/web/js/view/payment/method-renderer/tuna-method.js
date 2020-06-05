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
            isCPFValid: function (cpf) {
                cpf = cpf.replace(/[^\d]+/g, '');
                if (cpf == '') return false;
                if (cpf.length != 11 ||
                    cpf == "00000000000" ||
                    cpf == "11111111111" ||
                    cpf == "22222222222" ||
                    cpf == "33333333333" ||
                    cpf == "44444444444" ||
                    cpf == "55555555555" ||
                    cpf == "66666666666" ||
                    cpf == "77777777777" ||
                    cpf == "88888888888" ||
                    cpf == "99999999999")
                    return false;
                let add = 0;
                for (let i = 0; i < 9; i++)
                    add += parseInt(cpf.charAt(i)) * (10 - i);
                let rev = 11 - (add % 11);
                if (rev == 10 || rev == 11)
                    rev = 0;
                if (rev != parseInt(cpf.charAt(9)))
                    return false;
                add = 0;
                for (let i = 0; i < 10; i++)
                    add += parseInt(cpf.charAt(i)) * (11 - i);
                rev = 11 - (add % 11);
                if (rev == 10 || rev == 11)
                    rev = 0;
                if (rev != parseInt(cpf.charAt(10)))
                    return false;
                return true;
            },
            isCNPJValid: function (cnpj) {

                cnpj = cnpj.replace(/[^\d]+/g, '');

                if (cnpj == '') return false;

                if (cnpj.length != 14)
                    return false;

                if (cnpj == "00000000000000" ||
                    cnpj == "11111111111111" ||
                    cnpj == "22222222222222" ||
                    cnpj == "33333333333333" ||
                    cnpj == "44444444444444" ||
                    cnpj == "55555555555555" ||
                    cnpj == "66666666666666" ||
                    cnpj == "77777777777777" ||
                    cnpj == "88888888888888" ||
                    cnpj == "99999999999999")
                    return false;

                let tamanho = cnpj.length - 2
                let numeros = cnpj.substring(0, tamanho);
                let digitos = cnpj.substring(tamanho);
                let soma = 0;
                let pos = tamanho - 7;
                for (let i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2)
                        pos = 9;
                }
                let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado != digitos.charAt(0))
                    return false;

                tamanho = tamanho + 1;
                numeros = cnpj.substring(0, tamanho);
                soma = 0;
                pos = tamanho - 7;
                for (i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2)
                        pos = 9;
                }
                resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado != digitos.charAt(1))
                    return false;

                return true;

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
            isFieldsValid: function () {
                if (!$('#tuna_credit_card_holder')[0].value)
                    return false;

                let document = $('#tuna_credit_card_document')[0].value;
                if (!document || (!this.isCNPJValid(document) && !this.isCPFValid(document)))
                    return false;

                let cardNumber = this.onlyNumbers($('#tuna_credit_card_number')[0].value);
                if (!cardNumber || cardNumber.length != 16)
                    return false;

                if (!$('#tuna_credit_card_expiration_month')[0].value || !$('#tuna_credit_card_expiration_year')[0].value)
                    return false;

                if (!$('#tuna_credit_card_code')[0].value || $('#tuna_credit_card_code')[0].value.length < 3)
                    return false;

                return true;
            },
            placeOrder: function () {
                let self = this;
                var paymentData = quote.paymentMethod();
                var messageContainer = this.messageContainer;
                if (this.isFieldsValid()) {
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
                } else {
                    alert("invalid")
                }
            }
        });
    }
);
