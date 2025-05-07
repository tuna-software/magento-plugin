define(
    [
        'tuna_lib',
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
        'Magento_Catalog/js/price-utils'
    ],
    function (t, alert, $, quote, Component, setPaymentInformationAction, placeOrder, priceUtils) {
        'use strict';


        function getOrderTotal(){
            return parseFloat(quote.getTotals()()['total_segments'][quote.getTotals()()['total_segments'].length - 1].value, 10);
        }

        function getInstallments(value) {
            let feeList = window.checkoutConfig.payment.tunagateway.feeList;
            const referenceValue = value || getOrderTotal();

            let installmentIndex = 0;
            let result = [];
            feeList.forEach(value => {
                installmentIndex++;
                let finalText = '';
                let installmentValue, totalValue;
                if (value * 1 === 0) {
                    installmentValue = referenceValue / installmentIndex;
                    totalValue = referenceValue;
                } else {
                    let fee = (value * 1) / 100.00;
                    installmentValue = (referenceValue * (1 + fee)) / installmentIndex;
                    totalValue = (referenceValue * (1 + fee));
                }

                const minimumInstallmentValue = parseFloat(window.checkoutConfig.payment.tunagateway.minimum_installment_value ?? -1);

                if (minimumInstallmentValue > 0 && installmentIndex !== 1 && installmentValue < minimumInstallmentValue)
                    return;

                finalText = installmentIndex + 'x ' + priceUtils.formatPrice(installmentValue) + ' (' + priceUtils.formatPrice(totalValue) + ')';
                result.push({
                    'value': installmentIndex,
                    'text': finalText
                });
            });

            return result;
        }

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/credit',
            },

            initialize: function () {
                this._super();

                var sessionID = window.checkoutConfig.payment.tunagateway.sessionid;
                var env = window.checkoutConfig.payment.tunagateway.useSandboxBundle ? 'sandbox' : 'production';

                if (sessionID)
                    this.tuna = Tuna(sessionID, env);
            },

            afterRender: function () {
                $("#creditTitle").html(window.checkoutConfig.payment.tunagateway.title_credit);
                if (!this.tuna) {
                    $("#badNewsDiv").show();
                } else {
                    $(document).on('click', '.addNewCard', function () {
                        let targetElement = $('.viewSavedCards');
                        let newText = targetElement.html().replace('<span>2</span>', '');

                        targetElement.html(newText);

                        // Remove the event listener after execution
                        $(document).off('click', '.addNewCard');
                    });

                    $(document).on('click', '.viewSavedCards', function () {
                        let targetElement = $('.addNewCard');
                        let newText = targetElement.html().replace('<span>1</span>', '');

                        targetElement.html(newText);

                        // Remove the event listener after execution
                        $(document).off('click', '.viewSavedCards');
                    });

                    $("#divCreditOnly").one('click', function () {
                        $("button.defaultTunaButton").addClass("action primary checkout");
                    });



                    this.tuna.useDefaultForm("#creditCardPaymentDiv",
                        {
                            savedCardBoxTitle: "Cartões Salvos",
                            savedCardButtonTitle: "Usar cartão salvo",
                            newCardBoxTitle: "Novo cartão",
                            newCardButtonTitle: "Usar novo cartão",
                            button: { title: "Pagar", buttonClass : "action primary checkout paybutton" },
                            cardHolderName: { title: "Nome do titular", placeholder: "Nome como escrito no cartão" },
                            cardValidity: { title: "Validade", validationMessage: "Use um valor válido", placeholder: "mm/yyyy" },
                            cardCvv: { title: "CVV", validationMessage: "Por favor, informe o CVV", placeholder: "000" },
                            saveCard: { title: "Salvar cartão" },
                            cardList: { title: "Cartão salvo", cardCvv: { placeholder: "cvv" } },
                            cardNumber: { title: "Número do cartão", placeholder: "0000 0000 0000 0000" },
                            installment: {
                                title: "Parcelamento", options: getInstallments().map(i => ({ key: i.value, value: i.text }))
                            },
                            customAreas: [{
                                title: "Dados do comprador",
                                resultingObject: "customer",
                                fields: {
                                    document: {
                                        title: "CPF",
                                        buyerDocumentFormatter: this.tuna.getBuyerDocumentFormatter("pt-BR"),
                                        cleanMask: true,
                                        placeholder: "000.000.000-00"
                                    }
                                }
                            }],
                            checkoutCallback: response => this.placeOrder(response)
                        }
                    );
                }
            },

            isActive: function () {
                let tunaGateway = window?.checkoutConfig?.payment?.tunagateway;

                return tunaGateway?.allow_card === "1" && tunaGateway?.tuna_active !== "1";
            },

            placeOrder: function (tunaResponse) {
                if (tunaResponse?.success) {

                    let paymentData = quote.paymentMethod();
                    let messageContainer = this.messageContainer;

                    const {cardData, tokenData} = tunaResponse

                    const buyerDocument = cardData?.customer?.document;

                    let creditCardData = JSON.stringify([{
                        "card_holder_name": cardData.cardHolderName,
                        "credit_card_expiration_month": cardData.expirationMonth,
                        "credit_card_expiration_year": cardData.expirationYear,
                        "credit_card_installments": cardData.installment,
                        "credit_card_amount": getOrderTotal(),
                        "credit_card_token": tokenData.token,
                        "credit_card_brand": tokenData.brand,
                      }]);

                    this.endOrder(paymentData, messageContainer, creditCardData, buyerDocument);
                } else {
                    alert({
                        title: $.mage.__('Ocorreu um erro no processamento'),
                        content: $.mage.__('Preencha corretamente o formulário')
                    });
                }
            },

            endOrder: function (paymentData, messageContainer, creditCardData, buyerDocument) {

                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) { delete paymentData.__disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) { delete paymentData.disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) { delete paymentData.title; }

                const additionalData = {
                    'buyer_document': buyerDocument,
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': creditCardData,
                    'buyer_name': "",
                    'is_boleto_payment': "false",
                    'is_crypto_payment': "false",
                    'is_pix_payment': "false",
                    'is_link_payment': "false",
                    'payment_type': "TUNA_ECAC",
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
