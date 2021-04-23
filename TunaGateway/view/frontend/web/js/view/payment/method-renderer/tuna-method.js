define(
    [
        'tuna_essential',
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
    ],
    function (tuna_essential, alert, $, quote, Component, setPaymentInformationAction, placeOrder) {
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
            $("#tuna_billing_address_zip").mask("99.999-999");

            $('#tuna_billing_address_phone').mask('(00) 0000-00009');

            $("#tuna_billing_address_phone").live("blur", function (event) {
                if ($(this).val().length == 15) {
                    $('#tuna_billing_address_phone').mask('(00) 00000-0009');
                } else {
                    $('#tuna_billing_address_phone').mask('(00) 0000-00009');
                }
            });


        });

        $('input[type=radio][name=billingAddress]').live("change", function () {
            $("#billingAddressFields").hide();
        });

        function cardRadioChanged() {
            if ($("#tuna_card_radio_saved").prop("checked")) {
                $("#creditCardPaymentDiv").show();
                $("#newCardDiv").hide();
                $("#lblHolderNameBoleto").hide();
                $("#lblHolderNameCard").show();
                $("#savedCardDiv").show();
                $("#boletoDiv").hide();
                $(".checkout").html("Pagar");
            } else if ($("#tuna_card_radio_new").prop("checked")) {
                $("#savedCardDiv").hide();
                $("#creditCardPaymentDiv").show();
                $("#lblHolderNameBoleto").hide();
                $("#lblHolderNameCard").show();
                $("#newCardDiv").show();
                $("#boletoDiv").hide();
                $(".checkout").html("Pagar");
            } else {
                $("#creditCardPaymentDiv").hide();
                $("#lblHolderNameCard").hide();
                $("#lblHolderNameBoleto").show();
                $("#boletoDiv").show();
                $(".checkout").html("Gerar boleto");
            }
        };

        $("#tuna_card_radio_new").live("change", cardRadioChanged);
        $("#tuna_card_radio_saved").live("change", cardRadioChanged);
        $("#tuna_boleto_radio").live("change", cardRadioChanged);


        function isEquivalent(a, b) {
            var aProps = Object.getOwnPropertyNames(a);
            var bProps = Object.getOwnPropertyNames(b);

            if (aProps.length != bProps.length) {
                return false;
            }

            for (var i = 0; i < aProps.length; i++) {
                var propName = aProps[i];

                if (a[propName] !== b[propName]) {
                    return false;
                }
            }

            return true;
        }

        $("#tuna_billing_address_country").live("change", _ => {
            // Fix unknow info
            if ($("#control").length == 1) {
                $("#control").remove();
                $("#tuna_billing_address_country").val("BR");
            }

            let selectCountryID = $("#tuna_billing_address_country option:selected").val();
            let countryRegions = window.checkoutConfig.countries.find(c => c.id == selectCountryID).regions;
            $('#tuna_billing_address_state').find('option').remove();
            countryRegions.forEach(region => {
                let option = new Option(region.name, region.id);
                $('#tuna_billing_address_state').append(option);
            });
        }
        );

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/tuna',
            },

            afterRender: function () {
                if (!window.checkoutConfig.payment.tunagateway.sessionid) {
                    if (!this.allowBoleto()) {
                        $("#tuna_payment_method_content").remove();
                        $("#badNewsDiv").show();
                        return;
                    } else {
                        $("#creditCardPaymentDiv").remove();
                        $("#tuna_savedCard_label").remove();
                        $("#tuna_newCard_label").remove();
                        $("#tuna_boleto_radio").prop("checked", true);
                        $("#boletoDiv").show();
                        return;
                    }
                }

                if (window.checkoutConfig.payment.tunagateway.is_user_logged_in) {
                    $("#tuna_card_radio_saved").prop("checked", true);
                } else {
                    $("#tuna_card_radio_new").prop("checked", true);
                    $("#tuna_card_radio_saved").prop("disabled", true);
                    $("#newCardDiv").show();
                    $("#savedCardDiv").hide();
                }

                if (!this.allowBoleto()) {
                    $("#tuna_boleto_label").remove();
                    $("#boletoDiv").remove();
                }

                if (!this.getStoredCreditCards() || this.getStoredCreditCards().length == 0) {
                    $("#tuna_savedCard_label").remove();
                    $("#tuna_card_radio_new").prop("checked", true);
                    $("#newCardDiv").show();
                }

            },
            enableBillingAddressFields: function () {
                $("#billingAddressFields").show();
                $("input[name='billingAddress']").prop("checked", false);
            },
            hideBillingAddressFields: function () {
                $('#billingAddressFields').hide();
            },
            allowBoleto: function () {
                return window.checkoutConfig.payment.tunagateway.allow_boleto &&
                    window.checkoutConfig.payment.tunagateway.allow_boleto === "1";
            },
            getStoredCreditCards: function () {
                return window.checkoutConfig.payment.tunagateway.savedCreditCards;
            },
            getBillingAddresses: function () {
                return window.checkoutConfig.payment.tunagateway.billingAddresses;
            },
            getCountries: function () {
                return window.checkoutConfig.countries;
            },
            getCreditCardFlag: function (brand) {
                return window.tunaImages[brand.toUpperCase()];
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            selectStoredCard: function (cc) {
                $(".CcCvv").hide();
                $("#tuna_card_cvv_" + cc.token).show();
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
            getInstallments: function () {
                let installments = [];
                for (let i = 1; i <= window.checkoutConfig.payment.tunagateway.installments; i++) {
                    installments.push(i);
                }
                return _.map(installments, function (value, key) {
                    return {
                        'value': value,
                        'text': value + 'x'
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
            endOrder: function (self, creditCardData, paymentData, messageContainer, isBoleto = false) {

                let additionalData = {
                    'buyer_document': $('#tuna_credit_card_document').val(),
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_token': isBoleto ? "" : creditCardData.token,
                    'credit_card_brand': isBoleto ? "" : creditCardData.brand,
                    'credit_card_expiration_month': isBoleto ? "" : creditCardData.expirationMonth,
                    'credit_card_expiration_year': isBoleto ? "" : creditCardData.expirationYear,
                    'credit_card_installments': isBoleto ? "1" : $('#tuna_credit_card_installments').val(),
                    'buyer_name': isBoleto ? $('#tuna_boleto_holder').val() : $('#tuna_credit_card_holder').val(),
                    'is_boleto_payment': isBoleto ? "true" : "false"
                };

                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': additionalData
                })).done(function () {
                    console.log("done set payment information");
                    delete paymentData['title'];
                    $.when(
                        placeOrder(paymentData, messageContainer)).done(function () {
                            console.log("done place order");
                            $.mage.redirect(window.checkoutConfig.tuna_payment);
                        });
                }).fail(function () {
                    alert({
                        title: $.mage.__('Algo deu errado'),
                        content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                    });
                }).always(function () {
                    console.log("always");
                    // fullScreenLoader.stopLoader();
                });
            },
            isUsingSavedCard: function () {
                return window.checkoutConfig.payment.tunagateway.is_user_logged_in &&
                    $("#tuna_card_radio_saved").prop("checked");
            },
            isUsingNewCard: function () {
                return window.checkoutConfig.payment.tunagateway.is_user_logged_in &&
                    $("#tuna_card_radio_new").prop("checked");
            },
            isBoletoPayment: function () {
                return $("#tuna_boleto_radio").prop("checked");
            },
            getSelectedCardToken: function () {
                return $("input[name='storedCard']:checked").attr("id").substring(10, $("input[name='storedCard']:checked").attr("id").length);
            },
            isFieldsValid: function () {

                if (this.isUsingNewCard()) {

                    if (!$('#tuna_credit_card_holder')[0].value)
                        return "holderInvalidInfo";

                    let cardNumber = this.onlyNumbers($('#tuna_credit_card_number')[0].value);
                    if (!cardNumber || cardNumber.length != 16)
                        return "creditCardInvalidInfo";

                    let expirationYear = $('#tuna_credit_card_expiration_year')[0].value;
                    let expirationMonth = $('#tuna_credit_card_expiration_month')[0].value;

                    if (!expirationMonth || !expirationYear)
                        return "validityDateInvalidInfo";

                    let expireDate = new Date(expirationYear, parseInt(expirationMonth)-1);
                    if (expireDate < new Date())
                        return "validityDateInvalidInfo";

                    if (!$("#tuna_credit_card_code")[0].value || $("#tuna_credit_card_code")[0].value < 3)
                        return "cvvInvalidInfo";

                } else if (this.isUsingSavedCard()) {
                    if ($("input[name='storedCard']:checked").length > 0) {
                        if (!$("#tuna_card_cvv_" + this.getSelectedCardToken()).val())
                            return "cvvSavedInvalidInfo";
                    } else
                        return "noCreditCardSelected";
                } else if (this.isBoletoPayment()) {
                    if (!$('#tuna_boleto_holder')[0].value)
                        return "boletoHolderInvalidInfo";
                } else {
                    return "error";
                }

                let document = $('#tuna_credit_card_document')[0].value;
                if (!document || (!this.isCNPJValid(document) && !this.isCPFValid(document)))
                    return "cpfInvalidInfo";

                return null;
            },
            placeOrder: async function () {
                if ($("#customer-email").val() == "" && $(".authentication-wrapper").css("display") == "block") {
                    alert({
                        title: $.mage.__('Algo deu errado'),
                        content: $.mage.__('Operação só pode ser realizada após o preenchimento do campo e-mail.')
                    });
                    return false;
                }
                let self = this;
                let paymentData = quote.paymentMethod();
                let messageContainer = this.messageContainer;
                let fieldCheckResponse = this.isFieldsValid();
                if (!fieldCheckResponse) {
                    let tunaLib = Tuna(window.checkoutConfig.payment.tunagateway.sessionid);
                    let tokenizator = tunaLib.tokenizator();

                    if (this.isUsingSavedCard()) {
                        let creditCardData = this.getStoredCreditCards().find(cc => cc.token === this.getSelectedCardToken());
                        let creditCardCvv = $(".CcCvv:visible").val();
                        let bindResponse = await tokenizator.bind(creditCardData.token, creditCardCvv);

                        if (bindResponse && bindResponse.code == 1)
                            self.endOrder(self, creditCardData, paymentData, messageContainer);
                        else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }

                    } else if (this.isBoletoPayment()) {
                        self.endOrder(self, null, paymentData, messageContainer, true);
                    } else {

                        let cardData = {
                            CardHolderName: $('#tuna_credit_card_holder').val(),
                            CardNumber: this.onlyNumbers($('#tuna_credit_card_number').val()),
                            ExpirationMonth: parseInt($('#tuna_credit_card_expiration_month').val()),
                            ExpirationYear: parseInt($('#tuna_credit_card_expiration_year').val()),
                            Cvv: $('#tuna_credit_card_code').val()
                        };

                        let generateResponse = await tokenizator.generate(cardData);

                        if (generateResponse.code == 1) {
                            let creditCardData = {
                                token: generateResponse.token,
                                brand: generateResponse.brand,
                                expirationMonth: cardData.ExpirationMonth,
                                expirationYear: cardData.ExpirationYear,
                            };
                            self.endOrder(self, creditCardData, paymentData, messageContainer);
                        }
                        else {
                            alert({
                                title: $.mage.__('Algo deu errado'),
                                content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                            });
                            return;
                        }
                    }
                } else {
                    let validationLabels = ["holderInvalidInfo", "cpfInvalidInfo", "cvvSavedInvalidInfo",
                        "boletoHolderInvalidInfo", "creditCardInvalidInfo", "validityDateInvalidInfo",
                        "cvvInvalidInfo", "noCreditCardSelected", "installmentsInvalidInfo"];

                    validationLabels.forEach(fieldID => {
                        $("#" + fieldID).hide();
                    });

                    $("#" + fieldCheckResponse).show();
                }
            }
        });
    }
);
