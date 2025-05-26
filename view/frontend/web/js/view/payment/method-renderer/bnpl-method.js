define(
    [
        "jquery",
        'Magento_Checkout/js/view/payment/default',
        "Magento_Checkout/js/action/place-order",
        'Magento_Checkout/js/model/payment/additional-validators',
        "Magento_Checkout/js/model/quote",
        "Magento_Checkout/js/model/full-screen-loader",
        "Magento_Checkout/js/action/redirect-on-success",
        'Klump_Payment/js/klump-config',
        'mage/url',
    ],
    function (
        $,
        Component,
        placeOrderAction,
        additionalValidators,
        quote,
        fullScreenLoader,
        redirectOnSuccessAction,
        klumpConfig,
        url,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Klump_Payment/payment/bnpl',
                icon: 'Klump_Payment/images/logo.svg',
            },

            redirectAfterPlaceOrder: false,

            getPaymentIcon: function() {
                return require.toUrl(this.icon);
            },

            initialize: function () {
                this._super();

                let myKlumpContainer = document.getElementById('klump__cms__checkout');

                if (!myKlumpContainer) {
                    myKlumpContainer = document.createElement('div');
                    myKlumpContainer.id = 'klump__cms__checkout';
                    document.body.appendChild(myKlumpContainer);
                }

                klumpConfig.loadScript();

                return this;
            },

            isActive: function () {
                return true;
            },

            getCode: function() {
                return "bnpl";
            },

            getTitle: function() {
              return 'Buy Now Pay Later (BNPL)';
            },

            placeOrder: function () {
                if (this.validate()) {
                    this.processKlumpPayment();
                }
                return false;
            },

            afterPlaceOrder: function () {
                // Empty function as we're handling everything in processKlumpPayment
            },

            processKlumpPayment: function () {
                if (!quote.getItems() || !quote.getItems().length) {
                    console.error('Cart is empty or invalid');
                    fullScreenLoader.stopLoader();

                    // Redirect to cart page
                    window.location.href = url.build('checkout/cart');
                    return false;
                }

                var checkoutConfig = window.checkoutConfig;
                var paymentData = quote.billingAddress();
                var klumpConfig = checkoutConfig.payment.bnpl;

                // Base URL for constructing item URLs and image URLs
                var baseUrl = window.location.origin;

                var customerData;
                if (checkoutConfig.isCustomerLoggedIn) {
                    customerData = checkoutConfig.customerData;
                    paymentData.email = customerData.email;
                } else {
                    paymentData.email = quote.guestEmail;
                }

                this.isPlaceOrderActionAllowed(false);
                var _this = this;

                if (typeof Klump === 'undefined') {
                    console.error('Klump is undefined');
                    return;
                }

                var quoteId = checkoutConfig.quoteItemData[0].quote_id // quote.getQuoteId()[0];

                // Fetching shipping cost
                var shippingCost = quote.shippingMethod().amount;

                // Fetching cart items
                var cartItems = quote.getItems();
                if (!cartItems.length) {
                    _this.messageContainer.addErrorMessage({
                        message: "Cart is empty."
                    });
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

                const payload = {
                    publicKey: klumpConfig.public_key,
                    data: {
                        amount: parseFloat(quote.totals().grand_total, 10),
                        currency: checkoutConfig.totalsData.quote_currency_code,
                        email: paymentData.email,
                        shipping_fee: shippingCost,
                        redirect_url: baseUrl + '/checkout/#confirmation',
                        meta_data: {
                            email: paymentData.email,
                            order_id: quoteId,
                            klump_plugin_source: 'magento-2',
                            klump_plugin_version: '0.1.0',
                        },
                        items: items
                    },
                    // Conditionally set merchant_reference
                    if (quoteId) {
                        data.merchant_reference = quoteId;
                    },
                    onSuccess: (data) => {
                        _this.isPlaceOrderActionAllowed(true);
                        placeOrderAction(this.getData())
                            .done(function () {
                                redirectOnSuccessAction.execute();
                            })
                            .fail(function (response) {
                                _this.messageContainer.addErrorMessage({
                                    message: "Error placing order: " + response
                                });
                            });
                    },
                    onError: (data) => {
                        console.error(data);
                        _this.messageContainer.addErrorMessage({
                            message: "Error, please try again"
                        });

                        fullScreenLoader.stopLoader();
                        _this.isPlaceOrderActionAllowed(true);
                        return false;
                    },
                    onLoad: (data) => {},
                    onOpen: (data) => {},
                    onClose: (data) => {
                        // Re-enable place order button if needed
                        _this.isPlaceOrderActionAllowed(true);
                        return false; // Prevent any default navigation
                    }
                }

                if (paymentData.telephone) {
                    if (paymentData.telephone.length > 11) {
                        payload.data.phone = '0' + paymentData.telephone.substring(paymentData.telephone.length - 10);
                    } else {
                        payload.data.phone = paymentData.telephone;
                    }
                }

                if (shippingCost) {
                    payload.data.shipping_fee = shippingCost;
                }

                if(customerData) {
                    if (customerData.firstname) {
                        payload.data.first_name = customerData.firstname;
                    }

                    if (customerData.lastname) {
                        payload.data.last_name = customerData.lastname;
                    }
                }

                try {
                    new Klump(payload);
                } catch (error) {
                    _this.isPlaceOrderActionAllowed(true);
                    _this.messageContainer.addErrorMessage({
                        message: "Failed to initialize payment. Please try again."
                    });
                }
            }
        });
    }
);
