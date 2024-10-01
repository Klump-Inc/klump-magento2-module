define(
    [
        "jquery",
        'Magento_Checkout/js/view/payment/default',
        "Magento_Checkout/js/action/place-order",
        'Magento_Checkout/js/model/payment/additional-validators',
        "Magento_Checkout/js/model/quote",
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
            },

            redirectAfterPlaceOrder: false,

            initialize: function () {
                this._super();

                $("head").append('<script src="https://staging-js.useklump.com/klump.js">');
                return this;
            },

            isActive: function () {
                return true;
            },

            getCode: function() {
                return "bnpl";
            },

            placeOrder: function () {
                if (this.validate()) {
                    this._super();
                }
                return true;
            },

            afterPlaceOrder: function () {
                var checkoutConfig = window.checkoutConfig;
                var paymentData = quote.billingAddress();
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

                if (Klump === 'undefined') {
                    console.log('Klump is undefined');
                    return;
                }
                const payload = {
                    publicKey: klumpConfig.public_key,
                    data: {
                        amount: parseFloat(quote.totals().grand_total, 10),
                        currency: checkoutConfig.totalsData.quote_currency_code,
                        phone: paymentData.telephone,
                        email: paymentData.email,
                        // merchant_reference: paymentData.id,
                        // shipping_fee: 100,
                        // first_name: 'John',
                        // last_name: 'Doe',
                        redirect_url: 'https://verygoodmerchant.com/checkout/confirmation',
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
                        items: [
                            // {
                            //     image_url:
                            //         'https://s3.amazonaws.com/uifaces/faces/twitter/ladylexy/128.jpg',
                            //     item_url: 'https://www.paypal.com/in/webapps/mpp/home',
                            //     name: 'Awesome item',
                            //     unit_price: 2000,
                            //     quantity: 2,
                            // }
                        ]
                    },
                    onSuccess: (data) => {
                        console.log('html onSuccess will be handled by the merchant', data);
                        return data;
                    },
                    onError: (data) => {
                        console.log('html onError will be handled by the merchant', data);
                    },
                    onLoad: (data) => {
                        console.log('html onLoad will be handled by the merchant', data);
                    },
                    onOpen: (data) => {
                        console.log('html OnOpen will be handled by the merchant', data);
                    },
                    onClose: (data) => {
                        console.log('html onClose will be handled by the merchant', data);
                    }
                }

                var klump = new Klump(payload);
            }
        });
    }
);
