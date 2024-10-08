define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'bnpl',
                component: 'Klump_Payment/js/view/payment/method-renderer/bnpl-method'
            }
        );
        return Component.extend({});
    }
);
