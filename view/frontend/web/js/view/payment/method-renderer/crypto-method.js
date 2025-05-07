define(
    [
        'Magento_Ui/js/modal/alert',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order'
    ],
    function (alert, $, quote, Component, setPaymentInformationAction, placeOrder) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tuna_TunaGateway/payment/crypto',
            },

            afterRender: function () {
                $("#cryptoTitle").html(window.checkoutConfig.payment.tunagateway.title_crypto);
            },

            isActive: function () {
                let tunaGateway = window?.checkoutConfig?.payment?.tunagateway;

                return tunaGateway?.allow_crypto === "1" && tunaGateway?.tuna_active !== "1";
            },

            placeOrder: async function () {
                let paymentData = quote.paymentMethod();
                let messageContainer = this.messageContainer;

                this.endOrder(paymentData, messageContainer);
            },

            endOrder: function (paymentData, messageContainer) {

                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) { delete paymentData.__disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) { delete paymentData.disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) { delete paymentData.title; }

                
                const additionalData = {
                    'buyer_document': "",
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': "[]",
                    'buyer_name':  "",
                    'is_boleto_payment': "false",
                    'is_crypto_payment': "true",
                    'is_pix_payment':"false",
                    'is_link_payment': "false",
                    'payment_type': "TUNA_ECRYPTO",
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
