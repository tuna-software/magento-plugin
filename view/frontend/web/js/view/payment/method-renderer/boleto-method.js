define(
    [
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
        'jquery_mask'
    ],
    function (alert, $, quote, Component, setPaymentInformationAction, placeOrder) {
        'use strict';

        function addCpfCnpjMask() {
            const CpfCnpjMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
            };
            const cpfCnpjpOptions = {
                onKeyPress: function (val, e, field, options) {
                    field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                }
            };
            try {
                $('#boleto_document').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);
            } catch (e) {
                console.log("applied");
            }
        }

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/boleto',
            },

            afterRender: function () {
                const CpfCnpjMaskBehavior = function (val) {
                    return val.replace(/\D/g, '').length <= 11 ? '000.000.000-009' : '00.000.000/0000-00';
                };
                const cpfCnpjpOptions = {
                    onKeyPress: function (val, e, field, options) {
                        field.mask(CpfCnpjMaskBehavior.apply({}, arguments), options);
                    }
                };
                $('#boleto_document').mask(CpfCnpjMaskBehavior, cpfCnpjpOptions);   
                $("#boletoTitle").html(window.checkoutConfig.payment.tunagateway.title_boleto);

                setTimeout(function () {
                    addCpfCnpjMask();
                },500);
            },

            isActive: function () {
                let tunaGateway = window?.checkoutConfig?.payment?.tunagateway;

                return tunaGateway?.allow_boleto === "1" && tunaGateway?.tuna_active !== "1";
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
                for (let i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2)
                        pos = 9;
                }
                resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado != digitos.charAt(1))
                    return false;

                return true;

            },

            getValidBoletoData: function () {
                let document = $("#boleto_document").val();
                let buyerName = $("#boleto_buyer_name").val();
                let isBoletoDataValid = true;

                const isValidDocument = this.isCNPJValid(document) || this.isCPFValid(document);

                if (!isValidDocument) {
                    $("#boletoDocumentInvalidInfo").show();
                    isBoletoDataValid = false;
                }

                if (!(buyerName?.trim())) {
                    $("#boletoBuyerNameInvalidInfo").show();
                    isBoletoDataValid = false;
                }

                if (isBoletoDataValid)
                    return {document, buyerName}
                else
                    return null;

            },

            placeOrder: function () {
                $("#boletoDocumentInvalidInfo").hide();
                $("#boletoBuyerNameInvalidInfo").hide();

                const validBoletoData = this.getValidBoletoData();

                if (validBoletoData) {

                    let paymentData = quote.paymentMethod();
                    let messageContainer = this.messageContainer;

                    this.endOrder(paymentData, messageContainer, validBoletoData);
                } else {
                    alert({
                        title: $.mage.__('Ocorreu um erro no processamento'),
                        content: $.mage.__('Preencha corretamente o formulário')
                    });
                }
            },

            endOrder: function (paymentData, messageContainer, boletoAdditionalData) {

                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) {
                    delete paymentData.__disableTmpl;
                }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) {
                    delete paymentData.disableTmpl;
                }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) {
                    delete paymentData.title;
                }


                const additionalData = {
                    'buyer_document': boletoAdditionalData.document,
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': "[]",
                    'buyer_name': boletoAdditionalData.buyerName,
                    'is_boleto_payment': "true",
                    'is_crypto_payment': "false",
                    'is_pix_payment': "false",
                    'is_link_payment': "false",
                    'payment_type': "TUNA_EBOL",
                };

                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': additionalData
                })).done(function () {
                    delete paymentData['title'];
                    $.when(placeOrder(paymentData, messageContainer)).done(function () {
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
                });
            },
        });
    }
);
