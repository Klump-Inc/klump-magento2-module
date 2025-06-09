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
                var checkoutConfig = window.checkoutConfig;
                var paymentData = quote.billingAddress();
                var klumpConfig = checkoutConfig.payment.bnpl;

                // Validate configuration first
                if (!klumpConfig || !klumpConfig.public_key) {
                    this.messageContainer.addErrorMessage({
                        message: "Klump payment is not properly configured. Please contact support."
                    });
                    return;
                }

                // Validate quote exists
                if (!quote.getQuoteId()) {
                    this.messageContainer.addErrorMessage({
                        message: "Your session has expired. Please refresh the page and try again."
                    });
                    window.location.reload();
                    return;
                }

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
                    this.messageContainer.addErrorMessage({
                        message: "Klump (BNPL) payment gateway is not available. Please try again or contact support."
                    });
                    this.isPlaceOrderActionAllowed(true);
                    return;
                }

                var quoteId = checkoutConfig.quoteItemData[0].quote_id;
                var shippingCost = quote.shippingMethod().amount;
                var cartItems = quote.getItems();

                if (!cartItems.length) {
                    _this.messageContainer.addErrorMessage({
                        message: "Cart is empty."
                    });
                    this.isPlaceOrderActionAllowed(true);
                    return;
                }

                // Constructing items array for Klump
                var items = cartItems.map(function(item) {
                    return {
                        name: item.name,
                        unit_price: (parseFloat(item.row_total_incl_tax) - parseFloat(item.discount_amount)) / item.qty,
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
                            ],
                            klump_plugin_source: 'magento',
                            klump_plugin_version: '1.0.1',
                        },
                        items: items
                    },
                    onSuccess: (data) => {
                        _this.isPlaceOrderActionAllowed(true);

                        // Validate quote before placing order
                        if (!quote.getQuoteId()) {
                            _this.messageContainer.addErrorMessage({
                                message: "Your session has expired. Please refresh the page and try again."
                            });
                            window.location.reload();
                            return;
                        }

                        placeOrderAction(this.getData())
                            .done(function () {
                                redirectOnSuccessAction.execute();
                            })
                            .fail(function (response) {
                                // Handle quote expiration specifically
                                if (response.responseJSON && response.responseJSON.message &&
                                    response.responseJSON.message.includes('No such entity with')) {
                                    _this.messageContainer.addErrorMessage({
                                        message: "Your session has expired. Please refresh the page and try again."
                                    });
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    _this.messageContainer.addErrorMessage({
                                        message: "Error placing order: " + (response.responseJSON ? response.responseJSON.message : response)
                                    });
                                }
                            });
                    },
                    onError: (data) => {
                        // Validate quote before placing order for failed payments
                        if (!quote.getQuoteId()) {
                            _this.messageContainer.addErrorMessage({
                                message: "Payment failed and your session has expired. Please refresh the page and try again."
                            });
                            window.location.reload();
                            return;
                        }

                        // Create order even for failed payments so they appear in admin
                        placeOrderAction(this.getData())
                            .done(function (orderId) {
                                _this.messageContainer.addErrorMessage({
                                    message: "Payment failed. Order #" + orderId + " has been created for follow-up."
                                });
                            })
                            .fail(function (response) {
                                // Handle quote expiration specifically
                                if (response.responseJSON && response.responseJSON.message &&
                                    response.responseJSON.message.includes('No such entity with')) {
                                    _this.messageContainer.addErrorMessage({
                                        message: "Payment failed and your session has expired. Please refresh the page and try again."
                                    });
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    _this.messageContainer.addErrorMessage({
                                        message: "Payment failed and order could not be created: " + (response.responseJSON ? response.responseJSON.message : response)
                                    });
                                }
                            });
                        _this.isPlaceOrderActionAllowed(true);
                    },
                    onLoad: (data) => {},
                    onOpen: (data) => {},
                    onClose: (data) => {
                        _this.isPlaceOrderActionAllowed(true);
                    }
                }

                // Add phone number if available
                if (paymentData.telephone) {
                    if (paymentData.telephone.length > 11) {
                        payload.data.phone = '0' + paymentData.telephone.substring(paymentData.telephone.length - 10);
                    } else {
                        payload.data.phone = paymentData.telephone;
                    }
                }

                // Add customer names if available
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
                        message: "Failed to initialize payment. Please check your configuration and try again."
                    });
                }
            }
        });
    }
);
