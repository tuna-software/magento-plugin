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
                template: 'Tuna_TunaGateway/payment/applepay',
                walletToken: '',
                walletReady: false,
                walletButtonMounted: false
            },

            afterRender: function () {
                if (!this.isActive()) {
                    return;
                }
                var tunaGateway = window?.checkoutConfig?.payment?.tunagateway || {};
                $('#applePayTitle').html(tunaGateway.title_applepay || 'Apple Pay');
                
                // Chamar apenas uma vez por instância - usar setTimeout para evitar race conditions
                var self = this;
                if (!this.renderScheduled) {
                    this.renderScheduled = true;
                    setTimeout(function() {
                        self.mountApplePayButton();
                    }, 100);
                }
            },

            isActive: function () {
                let tunaGateway = window?.checkoutConfig?.payment?.tunagateway;
                return tunaGateway?.allow_applepay === '1';
            },

            getApplePayLogo: function () {
                return require.toUrl('Tuna_TunaGateway/images/applepay-badge.svg');
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

            mountApplePayButton: function () {
                const container = document.querySelector('#applePayButtonContainer');
                
                // Verificar se botão já foi montado
                if (!container) {
                    return;
                }
                
                // Verificar se já existe botão dentro do container
                if (container.querySelector('#applepay-button') !== null) {
                    return;
                }
                
                // Verificar flag de renderização
                if (this.walletButtonMounted) {
                    return;
                }

                this.walletButtonMounted = true; // Marca logo para evitar duplicação
                
                const tunaGateway = window?.checkoutConfig?.payment?.tunagateway || {};
                const merchantId = tunaGateway?.merchant_id_applepay;
                const sessionId = tunaGateway?.sessionid;
                const useSandbox = tunaGateway?.useSandboxBundle;
                const total = this.getOrderTotal();

                if (!merchantId || !this.isActive()) {
                    return;
                }

                // Verificar se Tuna está disponível globalmente (como função construtora)
                if (typeof window.Tuna !== 'function') {
                    container.innerHTML = '<p style="color: red;">Erro ao carregar Apple Pay</p>';
                    this.walletButtonMounted = true;
                    return;
                }

                const env = useSandbox ? 'sandbox' : 'production';
                const tuna = window.Tuna(sessionId, env);
                const self = this;

                // Garantir que amount é válido - SALVAR NA INSTÂNCIA PARA EVITAR CLOSURE ISSUES
                self.orderAmount = parseFloat(total) || 0;
                
                if (self.orderAmount <= 0) {
                    container.innerHTML = '<p style="color: red;">Erro: valor do pedido inválido</p>';
                    this.walletButtonMounted = true;
                    return;
                }

                const amountStr = self.orderAmount.toFixed(2);

                const applePaySettings = {
                    buttom: {
                        selector: '#applePayButtonContainer'
                    },
                    applePayCallback: function(result, apiVersion) {
                        try {
                            const tokenCandidates = [
                                result?.details?.token?.paymentData,
                                result?.payment?.token?.paymentData,
                                result?.payment?.token,
                                result?.token?.paymentData,
                                result?.token,
                                result?.details?.token
                            ];

                            let tokenData = tokenCandidates.find(function (candidate) {
                                return candidate !== null && candidate !== undefined && candidate !== '';
                            });
                            
                            if (tokenData) {
                                self.walletToken = typeof tokenData === 'string' ? tokenData : JSON.stringify(tokenData);
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
                    },
                    currencyCode: 'BRL',
                    countryCode: 'BR',
                    total: {
                        label: 'Compra em ' + window.location.hostname,
                        amount: {
                            value: amountStr,
                            currency: 'BRL'
                        }
                    },
                    cart: {
                        lineItems: [],
                        total: {
                            label: 'Compra em ' + window.location.hostname,
                            amount: {
                                value: amountStr,
                                currency: 'BRL'
                            }
                        }
                    },
                    merchantId: merchantId,
                    displayName: window.location.hostname,
                    api: 1
                };
                
                try {
                    const applePayPromise = tuna.useApplePay(applePaySettings);
                    
                    if (applePayPromise && typeof applePayPromise.then === 'function') {
                        applePayPromise.then(function(isReady) {
                            if (!isReady) {
                                if (container) {
                                    container.innerHTML = '<p style="color: #999;">Apple Pay não disponível neste navegador</p>';
                                }
                            }
                        }).catch(function(e) {
                            if (container) {
                                container.innerHTML = '<p style="color: red;">Erro ao carregar Apple Pay: ' + (e.message || 'desconhecido') + '</p>';
                            }
                        });
                    }
                } catch (e) {
                    if (container) {
                        container.innerHTML = '<p style="color: red;">Erro: ' + (e.message || 'desconhecido') + '</p>';
                    }
                }

                this.walletButtonMounted = true;
            },

            placeOrder: function () {
                let paymentData = quote.paymentMethod();
                let messageContainer = this.messageContainer;

                if (!this.walletToken) {
                    alert({
                        title: $.mage.__('Apple Pay ainda nao confirmado'),
                        content: $.mage.__('Finalize a autenticacao no Apple Pay antes de continuar.')
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
                    'is_googlepay_payment': 'false',
                    'is_applepay_payment': 'true',
                    'wallet_token_provider': 'ApplePay',
                    'wallet_token': this.walletToken,
                    'payment_type': 'TUNA_EAPPLEPAY'
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
                }).fail(function (result) {
                    alert({
                        title: $.mage.__('Algo deu errado'),
                        content: $.mage.__('Ocorreu um erro no processamento. Por favor, tente novamente')
                    });
                });
            }
        });
    }
);
