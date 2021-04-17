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
                type: 'aditumboleto',
                component: 'Aditum_Payment/js/view/payment/method-renderer/aditumboleto'
            }
        );
        return Component.extend({});
    }
);
