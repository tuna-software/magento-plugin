define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tuna',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/tuna-method'
            },
            {
                type: 'boleto',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/boleto-method'
            },
            {
                type: 'credit',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/credit-method'
            },
            {
                type: 'crypto',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/crypto-method'
            },
            {
                type: 'link',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/link-method'
            },
            {
                type: 'pix',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/pix-method'
            }
        );
        return Component.extend({});
    }
);