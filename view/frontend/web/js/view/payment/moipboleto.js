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
                type: 'moipboleto',
                component: 'Aditum_Payment/js/view/payment/method-renderer/moipboleto'
            }
        );
        return Component.extend({});
    }
);
