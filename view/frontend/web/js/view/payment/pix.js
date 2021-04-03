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
                type: 'pix',
                component: 'Tatix_PIX/js/view/payment/method-renderer/pix-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);