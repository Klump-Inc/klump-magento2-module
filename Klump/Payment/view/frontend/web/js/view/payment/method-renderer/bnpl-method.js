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

                let klumpCheckout = document.getElementById('klump__checkout');

                if (!klumpCheckout) {
                    klumpCheckout = document.createElement('div');
                    klumpCheckout.id = 'klump__checkout';
                    document.body.appendChild(klumpCheckout);
                }

                let script = document.createElement('script');
                script.src = "https://staging-js.useklump.com/klump.js";
                script.onload = function() {
                    console.log('Klump script loaded successfully.');
                };
                script.onerror = function() {
                    console.error('Failed to load Klump script.');
                };
                document.head.appendChild(script);

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

                // Base URL for constructing item URLs and image URLs
                var baseUrl = window.location.origin;

                if (checkoutConfig.isCustomerLoggedIn) {
                    var customerData = checkoutConfig.customerData;
                    paymentData.email = customerData.email;
                } else {
                    paymentData.email = quote.guestEmail;
                }

                console.log('quoteData', checkoutConfig)

                // var orderId = checkoutConfig.quoteData.entity_id

                var _this = this;
                _this.isPlaceOrderActionAllowed(false);

                if (typeof Klump === 'undefined') {
                    console.error('Klump is undefined');
                    return;
                }

                var quoteId = checkoutConfig.quoteItemData[0].quote_id // quote.getQuoteId()[0];

                // Fetching cart items
                var cartItems = quote.getItems();
                if (!cartItems.length) {
                    console.error('Cart is empty');
                    return;
                }

                // Constructing items array for Klump
                var items = cartItems.map(function(item) {
                    return {
                        name: item.name,
                        unit_price: (parseFloat(item.row_total_incl_tax) - parseFloat(item.discount_amount)) / item.qty, // Ensure correct price attribute according to your Magento setup
                        quantity: item.qty,
                        image_url: item.thumbnail,
                        item_url: baseUrl + item.product.request_path,
                    };
                });

                let phone;

                if (paymentData.telephone) {
                    if (paymentData.telephone.length > 11) {
                        phone = '0' + paymentData.telephone.substring(paymentData.telephone.length - 10);
                    } else {
                        phone = paymentData.telephone;
                    }
                }

                const payload = {
                    publicKey: klumpConfig.public_key,
                    data: {
                        amount: parseFloat(quote.totals().grand_total, 10),
                        currency: checkoutConfig.totalsData.quote_currency_code,
                        phone: phone,
                        email: paymentData.email,
                        // merchant_reference: orderId,
                        // shipping_fee: 100,
                        // first_name: 'John',
                        // last_name: 'Doe',
                        redirect_url: 'https://verygoodmerchant.com/checkout/confirmation',
                        meta_data: {
                            quote_id: quoteId,
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
                        items: items
                    },
                    onSuccess: (data) => {
                        console.log('html onSuccess will be handled by the merchant', data);
                        // return data;
                        // Ensure to redirect or clear actions post order if needed
                        fullScreenLoader.stopLoader();
                        _this.isPlaceOrderActionAllowed(true);
                        _this.messageContainer.addErrorMessage({
                            message: "Error, please try again"
                        });
                        // redirectOnSuccessAction.execute();
                    },
                    onError: (data) => {
                        console.error('html onError will be handled by the merchant', data);
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

                try {
                    console.log('Initializing Klump with payload:', payload);
                    new Klump(payload);
                    console.log('Klump initialized successfully.');
                } catch (error) {
                    console.error('Klump initialization error:', error);
                }
            }
        });
    }
);
