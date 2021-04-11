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
                type: 'aditumcc',
                component: 'Aditum_Payment/js/view/payment/method-renderer/moipcc'
            }
        );
        return Component.extend({});
    }
);
