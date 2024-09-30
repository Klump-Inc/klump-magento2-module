define(
    [
        "jquery",
        'Magento_Checkout/js/view/payment/default',
        "Magento_Checkout/js/action/place-order",
        'Magento_Checkout/js/model/payment/additional-validators',
        "Magento_Checkout/js/model/full-screen-loader",
        "Magento_Checkout/js/action/redirect-on-success",
    ],
    function (
        $,
        Component,
        placeOrderAction,
        additionalValidators,
        quote,
        fullScreenLoader,
        redirectOnSuccessAction
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Klump_Payment/payment/bnpl',
                // transactionResult: '',
                // customObserverName: null
            },

            redirectAfterPlaceOrder: false,

            initialize: function () {
                this._super();
                console.log('this is called.5')
                this.loadExternalScript('https://staging-js.useklump.com/klump.js');
                console.log('isChecked()', this.isChecked())

                return this;
            },

            loadExternalScript: function (url) {
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                document.head.appendChild(script);
            },

            isActive: function () {
                console.log('isChecked()', this.isChecked())
                return true;
            },

            placeOrder: function () {
                console.log('this is called, yearhu : placeOrder')
                if (this.validate()) {
                    this._super();
                }
                return true;
            },

            afterPlaceOrder: function () {
                console.log('this is called, yeah : afterPlaceOrder')
                var checkoutConfig = window.checkoutConfig;
                console.log('checkoutConfig', checkoutConfig);
                var paymentData = quote.billingAddress();

                console.log('paymentData', paymentData);

                var klumpConfig = checkoutConfig.payment.bnpl;
                console.log('klumpConfig', klumpConfig);

                if (checkoutConfig.isCustomerLoggedIn) {
                    var customerData = checkoutConfig.customerData;
                    paymentData.email = customerData.email;
                } else {
                    paymentData.email = quote.guestEmail;
                }

                var quoteId = checkoutConfig.quoteItemData[0].quote_id;

                var _this = this;
                _this.isPlaceOrderActionAllowed(false);
                var klumpInstance = new Klump({
                    publicKey: klumpConfig.public_key,
                    data: {
                        amount: parseFloat(quote.totals().grand_total, 10),
                        currency: checkoutConfig.totalsData.quote_currency_code,
                        phone: paymentData.telephone,
                        email: paymentData.email,
                        merchant_reference: paymentData.id,
                        meta_data: {
                            order_id: quoteId,
                            custom_fields: [
                                {
                                    display_name: "QuoteId",
                                    variable_name: "quote id",
                                    value: quoteId
                                },
                                {
                                    display_name: "Address",
                                    variable_name: "address",
                                    value: paymentData.street[0] + ", " + paymentData.street[1]
                                },
                                {
                                    display_name: "Postal Code",
                                    variable_name: "postal_code",
                                    value: paymentData.postcode
                                },
                                {
                                    display_name: "City",
                                    variable_name: "city",
                                    value: paymentData.city + ", " + paymentData.countryId
                                },
                                {
                                    display_name: "Plugin",
                                    variable_name: "plugin",
                                    value: "magento-2"
                                }
                            ]
                        },
                        // items: klp_payment_params.order_items,
                        // redirect_url: klp_payment_params.cb_url,
                    },
                    onSuccess: (data) => {
                        _this.isPlaceOrderActionAllowed(true);
                        return data;
                    },
                    onError: (data) => {
                        console.error('Klump Gateway Error has occurred.')
                    },
                    onLoad: (data) => {
                    },
                    onOpen: (data) => {
                    },
                    onClose: (data) => {
                    }
                });
            }
        });
    }
);
