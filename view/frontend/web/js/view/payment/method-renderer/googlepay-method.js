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
                template: 'Tuna_TunaGateway/payment/googlepay',
                walletToken: '',
                walletReady: false,
                walletButtonMounted: false
            },

            afterRender: function () {
                if (!this.isActive()) {
                    return;
                }
                var tunaGateway = window?.checkoutConfig?.payment?.tunagateway || {};
                $('#googlePayTitle').html(tunaGateway.title_googlepay || 'Google Pay');
                
                // Chamar apenas uma vez por instância - usar setTimeout para evitar race conditions
                var self = this;
                if (!this.renderScheduled) {
                    this.renderScheduled = true;
                    setTimeout(function() {
                        self.mountGooglePayButton();
                    }, 100);
                }
            },

            isActive: function () {
                let tunaGateway = window?.checkoutConfig?.payment?.tunagateway;
                return tunaGateway?.allow_googlepay === '1';
            },

            getGooglePayLogo: function () {
                return require.toUrl('Tuna_TunaGateway/images/googlepay-badge.svg');
            },

            getOrderTotal: function () {
                try {
                    const totals = quote.getTotals()();
                    const segments = totals?.total_segments || [];
                    return parseFloat(segments[segments.length - 1]?.value || totals?.grand_total || 0);
                } catch (e) {
                    return 0;
                }
            },

            mountGooglePayButton: function () {
                const container = document.querySelector('#googlePayButtonContainer');
                
                // Verificar se botão já foi montado
                if (!container) {
                    return;
                }
                
                this.walletButtonMounted = true; // Marca logo para evitar duplicação
                
                const tunaGateway = window?.checkoutConfig?.payment?.tunagateway || {};
                const merchantId = tunaGateway?.merchant_id_googlepay;
                const merchantName = tunaGateway?.merchant_name_googlepay;
                const sessionId = tunaGateway?.sessionid;
                const useSandbox = tunaGateway?.useSandboxBundle;

                if (!merchantId || !merchantName || !this.isActive()) {
                    return;
                }

                // Verificar se Tuna está disponível globalmente (como função construtora)
                if (typeof window.Tuna !== 'function') {
                    container.innerHTML = '<p style="color: red;">Erro ao carregar Google Pay</p>';
                    this.walletButtonMounted = true;
                    return;
                }

                const env = useSandbox ? 'sandbox' : 'production';
                const tuna = window.Tuna(sessionId, env);
                const self = this;

                container.innerHTML = '<div id="googlepay-button"></div>';

                const googlePaySettings = {
                    googlePayButtonContainerSelector: '#googlepay-button',
                    merchantInfo: {
                        merchantName: merchantName,
                        merchantId: merchantId
                    },
                    checkoutCallbackFunction: function(result) {
                        try {
                            const tokenData = result?.paymentMethodData?.tokenizationData?.token;

                            if (tokenData) {
                                self.walletToken = tokenData;
                                self.walletReady = true;
                                setTimeout(function() {
                                    self.placeOrder();
                                }, 300);
                                return true;
                            }
                            return false;
                        } catch (e) {
                            return false;
                        }
                    }
                };

                try {
                    if (typeof tuna.useGooglePay === 'function') {
                        const result = tuna.useGooglePay(googlePaySettings);
                        if (result && typeof result.then === 'function') {
                            result.then(function() {
                            }).catch(function(e) {});
                        }
                    }
                } catch (error) {}
                
                this.walletButtonMounted = true;
            },

            placeOrder: function () {
                let paymentData = quote.paymentMethod();
                let messageContainer = this.messageContainer;

                if (!this.walletToken) {
                    alert({
                        title: $.mage.__('Google Pay ainda nao confirmado'),
                        content: $.mage.__('Finalize a autenticacao no Google Pay antes de continuar.')
                    });
                    return;
                }

                this.endOrder(paymentData, messageContainer);
            },

            endOrder: function (paymentData, messageContainer) {
                if (Object.prototype.hasOwnProperty.call(paymentData, '__disableTmpl')) { delete paymentData.__disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'disableTmpl')) { delete paymentData.disableTmpl; }
                if (Object.prototype.hasOwnProperty.call(paymentData, 'title')) { delete paymentData.title; }

                const additionalData = {
                    'buyer_document': '',
                    'session_id': window.checkoutConfig.payment.tunagateway.sessionid,
                    'credit_card_data': '[]',
                    'buyer_name': '',
                    'is_boleto_payment': 'false',
                    'is_crypto_payment': 'false',
                    'is_pix_payment': 'false',
                    'is_link_payment': 'false',
                    'is_googlepay_payment': 'true',
                    'is_applepay_payment': 'false',
                    'wallet_token_provider': 'GooglePay',
                    'wallet_token': this.walletToken,
                    'payment_type': 'TUNA_EGOOGLEPAY'
                };

                const placeOrderPaymentData = Object.assign({}, paymentData, {
                    method: this.getCode(),
                    additional_data: additionalData
                });

                $.when(setPaymentInformationAction(messageContainer, {
                    'method': this.getCode(),
                    'additional_data': additionalData
                })).done(function () {
                    delete paymentData.title;
                    $.when(placeOrder(placeOrderPaymentData, messageContainer)).done(function () {
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
            }
        });
    }
);
