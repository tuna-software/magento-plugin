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
                type: 'credit',
                component: 'Tuna_TunaGateway/js/view/payment/method-renderer/tuna-method-credit'
            }
        );
        return Component.extend({});
    }
);