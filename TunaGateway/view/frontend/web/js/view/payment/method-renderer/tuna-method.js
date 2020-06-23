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

            var CpfCnpjMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
            },
                cpfCnpjpOptions = {
                    onKeyPress: function (val, e, field, options) {
                        field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                    }
                };

            $('#tuna_credit_card_document').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);

            $("#tuna_credit_card_code").mask("9999");
            $(".CcCvv").mask("9999");
            $("#tuna_credit_card_number").mask("9999 9999 9999 9999");

        });

        function cardRadioChanged() {
            if ($("#tuna_card_radio_saved").prop("checked")) {
                $("#newCardDiv").hide();
                $("#boletoDiv").hide();
                $("#savedCardDiv").show();
            } else if ($("#tuna_card_radio_new").prop("checked")) {
                $("#savedCardDiv").hide();
                $("#boletoDiv").hide();
                $("#newCardDiv").show();
            } else {
                $("#savedCardDiv").hide();
                $("#newCardDiv").hide();
                $("#boletoDiv").show();
            }
        };

        $("#tuna_card_radio_new").live("change", cardRadioChanged);
        $("#tuna_card_radio_saved").live("change", cardRadioChanged);
        $("#tuna_boleto_radio").live("change", cardRadioChanged);

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/tuna',
            },
            AMAZON_flag: window.tunaImages.AMAZON_flag,
            AMEX_flag: window.tunaImages.AMEX_flag,
            CIRRUS_flag: window.tunaImages.CIRRUS_flag,
            DINNERS_flag: window.tunaImages.DINNERS_flag,
            DIRECT_DEBIT_flag: window.tunaImages.DIRECT_DEBIT_flag,
            DISCOVER_flag: window.tunaImages.DISCOVER_flag,
            EBAY_flag: window.tunaImages.EBAY_flag,
            EWAY_flag: window.tunaImages.EWAY_flag,
            JCB_flag: window.tunaImages.JCB_flag,
            MAESTRO_flag: window.tunaImages.MAESTRO_flag,
            MASTERCARD_flag: window.tunaImages.MASTERCARD_flag,
            PAYPAL_flag: window.tunaImages.PAYPAL_flag,
            SAGE_flag: window.tunaImages.SAGE_flag,
            SHOPIFY_flag: window.tunaImages.SHOPIFY_flag,
            SKRILL_MONEYBOOKERS_flag: window.tunaImages.SKRILL_MONEYBOOKERS_flag,
            SKRILL_flag: window.tunaImages.SKRILL_flag,
            SOLO_2_flag: window.tunaImages.SOLO_2_flag,
            SOLO_flag: window.tunaImages.SOLO_flag,
            VISA_ELECTRON_flag: window.tunaImages.VISA_ELECTRON_flag,
            VISA_flag: window.tunaImages.VISA_flag,
            WESTERN_UNION_flag: window.tunaImages.WESTERN_UNION_flag,
            WORLDPAY_flag: window.tunaImages.WORLDPAY_flag,
            W_flag: window.tunaImages.W_flag,


            afterRender: function () {
                if (window.checkoutConfig.payment.tunagateway.is_user_logged_in) {
                    $("#tuna_card_radio_saved").prop("checked", true);
                } else {
                    $("#tuna_card_radio_new").prop("checked", true);
                    $("#tuna_card_radio_saved").prop("disabled", true);
                    $("#newCardDiv").show();
                    $("#savedCardDiv").hide();
                }
            },
            getStoredCreditCards: function () {
                return window.checkoutConfig.payment.tunagateway.savedCreditCards;
            },
            getCreditCardFlag: function (brand) {
                let brandDict = {};
                brandDict.VISA = window.tunaImages.VISA_flag;
                brandDict.MASTER = window.tunaImages.MASTERCARD_flag;
                brandDict.AMER = window.tunaImages.AMEX_flag;

                return brandDict[brand];
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            selectStoredCard: function (cc) {
                $(".CcCvv").hide();
                $("#tuna_card_cvv_" + cc.Token).show();
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
            endOrder: function (self, tunaCardToken, paymentData, messageContainer, isBoleto = false) {
                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': {
                        'credit_card_document': $('#tuna_credit_card_document')[0].value,
                        'credit_card_hash': window.checkoutConfig.payment.tunagateway.sessionid,
                        'credit_card_token': tunaCardToken,
                        'credit_card_holder_name': $('#tuna_credit_card_holder')[0].value,
                        'boleto_url': '',
                        'is_boleto_payment': isBoleto
                    }
                })).done(function () {
                    console.log("done set payment information");
                    $.when(placeOrder(paymentData, messageContainer)).done(function () {
                        console.log("done place order");
                        $.mage.redirect(window.checkoutConfig.tuna_payment);
                    });
                    //return;
                }).fail(function () {
                    console.log("fail");
                }).always(function () {
                    console.log("always");
                    // fullScreenLoader.stopLoader();
                });
            },
            isUsingSavedCard: function () {
                return window.checkoutConfig.payment.tunagateway.is_user_logged_in &&
                    $("#tuna_card_radio_saved").prop("checked");
            },
            isBoletoPayment: function () {
                return $("#tuna_boleto_radio").prop("checked");
            },
            getSelectedCardToken: function () {
                return $("input[name='storedCard']:checked").attr("id").substring(10, $("input[name='storedCard']:checked").attr("id").length);
            },
            isFieldsValid: function () {
                if (!$('#tuna_credit_card_holder')[0].value)
                    return false;

                let document = $('#tuna_credit_card_document')[0].value;
                if (!document || (!this.isCNPJValid(document) && !this.isCPFValid(document)))
                    return false;

                if (this.isUsingSavedCard()) {
                    if ($("input[name='storedCard']:checked")) {
                        if (!$("#tuna_card_cvv_" + this.getSelectedCardToken()).val())
                            return false;
                    } else
                        return false
                } else if (!this.isBoletoPayment()) {
                    let cardNumber = this.onlyNumbers($('#tuna_credit_card_number')[0].value);
                    if (!cardNumber || cardNumber.length != 16)
                        return false;

                    if (!$('#tuna_credit_card_expiration_month')[0].value || !$('#tuna_credit_card_expiration_year')[0].value)
                        return false;

                    if (!$('#tuna_credit_card_code')[0].value || $('#tuna_credit_card_code')[0].value.length < 3)
                        return false;
                }

                return true;
            },
            placeOrder: function () {
                let self = this;
                var paymentData = quote.paymentMethod();
                var messageContainer = this.messageContainer;
                if (this.isFieldsValid()) {

                    if (this.isUsingSavedCard()) {
                        self.endOrder(self, this.getSelectedCardToken(), paymentData, messageContainer);
                    } else if (this.isBoletoPayment()) {
                        self.endOrder(self, "", paymentData, messageContainer, true);
                    } else {
                        let data = {
                            tunaSessionId: window.checkoutConfig.payment.tunagateway.sessionid,
                            cardHolder: $('#tuna_credit_card_holder')[0].value,
                            cardNumber: this.onlyNumbers($('#tuna_credit_card_number')[0].value),
                            creditCardDocument: this.onlyNumbers($('#tuna_credit_card_document')[0].value),
                            cvv: $('#tuna_credit_card_code')[0].value,
                            expirationMonth: $('#tuna_credit_card_expiration_month')[0].value,
                            expirationYear: $('#tuna_credit_card_expiration_year')[0].value
                        };
                        $.post("http://tuna.mypig.com.br/Card/SaveData", data, function (returnedData) {
                            self.endOrder(self, returnedData.sessionID, paymentData, messageContainer);
                        });
                    }
                } else {
                    alert("invalid")
                }
            }
        });
    }
);
