let tunaLibBoleto;
let methodBoleto = "BoletoOnly";
if (window.checkoutConfig.payment.tunagateway.useSandboxBundle)
tunaLibBoleto = "tuna_essential_sandbox";
else
tunaLibBoleto = "tuna_essential";

define(
    [
        tunaLibBoleto,
        'tuna_checkout_info',
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
        'Magento_Catalog/js/price-utils'
    ],
    function (tuna_essential, tuna_checkout_info, alert, $, quote, Component, setPaymentInformationAction, placeOrder, priceUtils) {
        'use strict';
        require(['jquery', 'jquery_mask'], function ($) {

            const CpfCnpjMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
            };
            const cpfCnpjpOptions = {
                onKeyPress: function (val, e, field, options) {
                    field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                }
            };

          
            $("#div"+methodBoleto + " #tuna_billing_address_zip").mask("99.999-999");

            $('#div'+methodBoleto + ' #tuna_billing_address_phone').mask('(00) 0000-00009');

            $("#div"+methodBoleto + " #tuna_billing_address_phone").live("blur", function (event) {
                if ($(this).val().length == 15) {
                    $('#div'+methodBoleto + ' #tuna_billing_address_phone').mask('(00) 00000-0009');
                } else {
                    $('#div'+methodBoleto + ' #tuna_billing_address_phone').mask('(00) 0000-00009');
                }
            });
            $(document).on('change', 'input[name$="[qty]"]', function () {
                var deferred = $.Deferred();
                getTotalsAction([], deferred);
            });
        });

        $('input[type=radio][name=billingAddress]').live("change", function () {
            $("#div"+methodBoleto + " #billingAddressFields").hide();
        });

        $("#div"+methodBoleto + " #checkmo").live("click", resetOrderInfo);
              
        function getOrderTotal() {
            return parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
        }

        $("#div"+methodBoleto + " #tuna_billing_address_country").live("change", _ => {
            // Fix unknown info
            if ($("#div"+methodBoleto + " #control").length == 1) {
                $("#div"+methodBoleto + " #control").remove();
                $("#div"+methodBoleto + " #tuna_billing_address_country").val("BR");
            }

            let selectCountryID = $("#div"+methodBoleto + " #tuna_billing_address_country option:selected").val();
            let countryRegions = window.checkoutConfig.countries.find(c => c.id == selectCountryID).regions;
            $('#div'+methodBoleto + ' #tuna_billing_address_state').find('option').remove();
            countryRegions.forEach(region => {
                let option = new Option(region.name, region.id);
                $('#div'+methodBoleto + ' #tuna_billing_address_state').append(option);
            });
        });

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/boleto',
            },
            afterRender: function () {
                //$('#div'+methodBoleto + ' #tuna').prop('checked', true);
                $("#div"+methodBoleto + " #lblTunaPaymentTitleBoleto").html(window.checkoutConfig.payment.tunagateway.title_boleto);
                if (!window.checkoutConfig.payment.tunagateway.sessionid) {
                    if (!this.isTunaActive()) {
                        $("#div"+methodBoleto + " #tuna_payment_method_content").remove();
                        $("#div"+methodBoleto + " #badNewsDiv").show();
                        return;
                    } else {
                        $("#div"+methodBoleto + " #boletoDiv").show();
                        return;
                    }
                }

                if (!this.isTunaActive()) {
                    $("#div"+methodBoleto + " #tuna_boleto_label").remove();
                    $("#div"+methodBoleto + " #boletoDiv").remove();
                }                
            },
            enableBillingAddressFields: function () {
                $("#div"+methodBoleto + " #billingAddressFields").show();
                $("input[name='billingAddress']").prop("checked", false);
            },
            disableAll:function(){
                DisableAllMethods();
            },
            hideBillingAddressFields: function () {
                $('#div'+methodBoleto + ' #billingAddressFields').hide();
            },          
            isTunaActive: function () {                                
                return (window.checkoutConfig.payment.tunagateway.allow_boleto &&
                    window.checkoutConfig.payment.tunagateway.allow_boleto === "1") && !(window.checkoutConfig.payment.tunagateway.tuna_active &&
                        window.checkoutConfig.payment.tunagateway.tuna_active === "1") ;
            },           
            getBillingAddresses: function () {
                return window.checkoutConfig.payment.tunagateway.billingAddresses;
            },
            getCountries: function () {
                return window.checkoutConfig.countries;
            },           
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
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
            endOrder: function (self, creditCardData, secondCreditCardData, paymentData, messageContainer, isBoleto = false, isPix = false, isCrypto = false, isLink = false) {
             
                let creditCards = [];
               
                const additionalData = {
                    'buyer_document': $('#div'+methodBoleto + ' #tuna_credit_card_document_boleto').val(),
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': JSON.stringify(creditCards),
                    'buyer_name':  $('#div'+methodBoleto + ' #tuna_boleto_holder_boleto').val() ,
                    'is_boleto_payment': "true" ,
                    'is_crypto_payment': "false",
                    'is_pix_payment': "false",
                    'is_link_payment': "false",
                    'payment_type': "TUNA_EBOL" ,
                };

                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) { delete paymentData.__disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) { delete paymentData.disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) { delete paymentData.title; }
                $.when(setPaymentInformationAction(messageContainer, {
                    'method': 'boleto',
                    'additional_data': additionalData
                })).done(function () {
                    delete paymentData['title'];
                    $.when(
                        placeOrder(paymentData, messageContainer)).done(function () {
                            $.mage.redirect(window.checkoutConfig.tuna_payment);
                        }).fail(function (result) {
                            alert({
                                title: $.mage.__('Ocorreu um erro no processamento'),
                                content: $.mage.__(result.responseJSON.message)
                            });
                        });
                }).fail(function () {
                    alert({
                        title: $.mage.__('Algo deu errado'),
                        content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                    });
                }).always(function () {

                    // fullScreenLoader.stopLoader();
                });
            },           
            isBoletoPayment: function () {
                return true;
            },            
            
            isFieldsValid: function () {
                
                    if (!$('#div'+methodBoleto + ' #tuna_boleto_holder_boleto')[0].value)
                        return "boletoHolderInvalidInfo";                     

                let document = $('#div'+methodBoleto + ' #tuna_credit_card_document_boleto')[0].value;
                const hasDocument = !!document;
                
                const isValidDocument = this.isCNPJValid(document) || this.isCPFValid(document);
                if (!(hasDocument && isValidDocument)) {
                    return "cpfInvalidInfo";
                }

                return null;
            },            
            placeOrder: async function () {
                if ($("#customer-email").val() == "" && $("#authentication-wrapper").css("display") == "block") {
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
                        self.endOrder(self, null, null, paymentData, messageContainer, true, false, false, false);
                    
                } else {
                    let validationLabels = [
                        "secondHolderInvalidInfo", "holderInvalidInfo", "cpfInvalidInfo_boleto", "cvvSavedInvalidInfo",
                        "BboletoHolderInvalidInfo_boleto", "secondCreditCardInvalidInfo", "creditCardInvalidInfo", "secondValidityDateInvalidInfo",
                        "validityDateInvalidInfo", "secondCvvInvalidInfo", "cvvInvalidInfo", "secondNoCreditCardSelected",
                        "noCreditCardSelected", "installmentsInvalidInfo"];

                    validationLabels.forEach(fieldID => {
                        $("#div"+methodBoleto + " #" + fieldID).hide();
                    });

                    $("#div"+methodBoleto + " #" + fieldCheckResponse).show();
                }
            }
        });
    }
);
