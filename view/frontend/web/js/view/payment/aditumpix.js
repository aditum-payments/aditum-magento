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
                type: 'aditumpix',
                component: 'AditumPayment_Magento2/js/view/payment/method-renderer/aditumpix'
            }
        );
        return Component.extend({});
    }
);
