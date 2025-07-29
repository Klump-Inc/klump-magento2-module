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
        mageUrl,
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

            redirectToCustomAction: function (url) {
                fullScreenLoader.startLoader();
                window.location.replace(mageUrl.build(url));
            },

            afterPlaceOrder: function () {
                // Empty function as we're handling everything in processKlumpPayment
            },

            /**
             * Validate payment configuration
             * @returns {boolean}
             */
            validateConfiguration: function() {
                var klumpConfig = window.checkoutConfig.payment.bnpl;

                if (!klumpConfig || !klumpConfig.public_key) {
                    this.showError("Klump payment is not properly configured. Please contact support.");
                    return false;
                }

                if (typeof Klump === 'undefined') {
                    this.showError("Klump (BNPL) payment gateway is not available. Please try again or contact support.");
                    return false;
                }

                return true;
            },

            /**
             * Validate quote and session
             * @returns {boolean}
             */
            validateQuote: function() {
                if (!quote.getQuoteId()) {
                    this.showError("Your session has expired. Please refresh the page and try again.");
                    this.redirectToCustomAction(window.checkoutConfig.payment.bnpl.recreate_quote_url);
                    return false;
                }

                var cartItems = quote.getItems();
                if (!cartItems.length) {
                    this.showError("Cart is empty.");
                    return false;
                }

                return true;
            },

            /**
             * Get customer data safely
             * @returns {Object}
             */
            getCustomerData: function() {
                var checkoutConfig = window.checkoutConfig;
                var paymentData = quote.billingAddress();
                var customerData = null;

                if (checkoutConfig.isCustomerLoggedIn) {
                    customerData = checkoutConfig.customerData;
                    paymentData.email = customerData.email;
                } else {
                    paymentData.email = quote.guestEmail;
                }

                return { paymentData, customerData };
            },

            /**
             * Build payment items array
             * @returns {Array}
             */
            buildPaymentItems: function() {
                var baseUrl = window.location.origin;
                var cartItems = quote.getItems();

                return cartItems.map(function(item) {
                    return {
                        name: item.name,
                        unit_price: (parseFloat(item.row_total_incl_tax) - parseFloat(item.discount_amount)) / item.qty,
                        quantity: item.qty,
                        image_url: item.thumbnail,
                        item_url: baseUrl + item.product.request_path,
                    };
                });
            },

            /**
             * Build payment payload
             * @param {Object} customerInfo
             * @returns {Object}
             */
            buildPaymentPayload: function(customerInfo) {
                var checkoutConfig = window.checkoutConfig;
                var klumpConfig = checkoutConfig.payment.bnpl;

                // Add null checks
                if (!checkoutConfig.quoteItemData || !checkoutConfig.quoteItemData[0]) {
                    throw new Error('Quote data not available');
                }
                
                var { paymentData, customerData } = customerInfo;
                var quoteId = checkoutConfig.quoteItemData[0].quote_id;
                var baseUrl = window.location.origin;

                const payload = {
                    publicKey: klumpConfig.public_key,
                    data: {
                        amount: parseFloat(quote.totals().grand_total, 10),
                        currency: checkoutConfig.totalsData.quote_currency_code,
                        email: paymentData.email,
                        shipping_fee: quote.shippingMethod().amount,
                        redirect_url: baseUrl + '/checkout/#confirmation',
                        meta_data: {
                            quote_id: quoteId,
                            custom_fields: this.buildCustomFields(paymentData, quoteId),
                            klump_plugin_source: 'magento',
                            klump_plugin_version: '1.0.4',
                        },
                        items: this.buildPaymentItems()
                    },
                    onSuccess: this.handlePaymentSuccess.bind(this),
                    onError: this.handlePaymentError.bind(this),
                    onLoad: function(data) {},
                    onOpen: function(data) {},
                    onClose: this.handlePaymentClose.bind(this)
                };

                // Add phone number safely
                this.addPhoneNumber(payload, paymentData);

                // Add customer names safely
                this.addCustomerNames(payload, customerData);

                return payload;
            },

            /**
             * Build custom fields for metadata
             * @param {Object} paymentData
             * @param {String} quoteId
             * @returns {Array}
             */
            buildCustomFields: function(paymentData, quoteId) {
                return [
                    {
                        display_name: "QuoteId",
                        variable_name: "quote id",
                        value: quoteId
                    },
                    {
                        display_name: "Address",
                        variable_name: "address",
                        value: paymentData.street ? paymentData.street.join(', ') : ''
                    },
                    {
                        display_name: "Postal Code",
                        variable_name: "postal_code",
                        value: paymentData.postcode || ''
                    },
                    {
                        display_name: "City",
                        variable_name: "city",
                        value: (paymentData.city || '') + ", " + (paymentData.countryId || '')
                    },
                    {
                        display_name: "Plugin",
                        variable_name: "plugin",
                        value: "magento-2"
                    }
                ];
            },

            /**
             * Add phone number to payload safely
             * @param {Object} payload
             * @param {Object} paymentData
             */
            addPhoneNumber: function(payload, paymentData) {
                if (paymentData.telephone && paymentData.telephone.length >= 11) {
                    if (paymentData.telephone.length > 11) {
                        payload.data.phone = '0' + paymentData.telephone.substring(paymentData.telephone.length - 10);
                    } else {
                        payload.data.phone = paymentData.telephone;
                    }
                }
            },

            /**
             * Add customer names to payload safely
             * @param {Object} payload
             * @param {Object} customerData
             */
            addCustomerNames: function(payload, customerData) {
                if (customerData) {
                    if (customerData.firstname) {
                        payload.data.first_name = customerData.firstname;
                    }
                    if (customerData.lastname) {
                        payload.data.last_name = customerData.lastname;
                    }
                }
            },

            /**
             * Handle successful payment
             * @param {Object} data
             */
            handlePaymentSuccess: function(data) {
                this.isPlaceOrderActionAllowed(true);

                redirectOnSuccessAction.execute();

                // if (!this.validateQuoteBeforeOrder()) {
                //     return;
                // }

                // var self = this;
                // placeOrderAction(this.getData())
                //     .done(function () {
                //         redirectOnSuccessAction.execute();
                //     })
                //     .fail(function (response) {
                //         self.handleOrderPlacementError(response);
                //     });
            },

            /**
             * Handle payment error
             * @param {Object} data
             */
            handlePaymentError: function(data) {
                this.isPlaceOrderActionAllowed(true);

                this.showError("Payment failed. Your order has been created and will be updated based on payment status.");
    
                // Optionally redirect to order view or cart
                setTimeout(function() {
                    window.location.href = mageUrl.build('sales/order/history/');
                }, 3000);

                // var self = this;
                // // Create order even for failed payments
                // placeOrderAction(this.getData())
                //     .done(function (orderId) {
                //         self.showError("Payment failed. Order #" + orderId + " has been created for follow-up.");
                //         self.redirectToCustomAction(window.checkoutConfig.payment.bnpl.recreate_quote_url);
                //     })
                //     .fail(function (response) {
                //         self.handleOrderPlacementError(response, "Payment failed and order could not be created: ");
                //     });

                // this.isPlaceOrderActionAllowed(true);
            },

            /**
             * Handle payment modal close
             * @param {Object} data
             */
            handlePaymentClose: function(data) {
                this.isPlaceOrderActionAllowed(true);
            },

            /**
             * Validate quote before placing order
             * @returns {boolean}
             */
            validateQuoteBeforeOrder: function() {
                if (!quote.getQuoteId()) {
                    this.showError("Your session has expired. Please refresh the page and try again.");
                    window.location.reload();
                    return false;
                }
                return true;
            },

            /**
             * Handle order placement errors
             * @param {Object} response
             * @param {String} prefix
             */
            handleOrderPlacementError: function(response, prefix = "Error placing order: ") {
                if (response.responseJSON && response.responseJSON.message &&
                    response.responseJSON.message.includes('No such entity with')) {
                    this.showError("Your session has expired. Please refresh the page and try again.");
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    var errorMessage = response.responseJSON ? response.responseJSON.message : response;
                    this.showError(prefix + errorMessage);
                }
            },

            /**
             * Show error message
             * @param {String} message
             */
            showError: function(message) {
                this.messageContainer.addErrorMessage({ message: message });
            },

            /**
             * Main payment processing method - refactored
             */
            processKlumpPayment: function () {
                // Validate configuration
                if (!this.validateConfiguration()) {
                    return;
                }

                // Validate quote and cart
                if (!this.validateQuote()) {
                    return;
                }

                // Get customer data
                var customerInfo = this.getCustomerData();

                // Disable place order button
                this.isPlaceOrderActionAllowed(false);

                var self = this;

                // CREATE ORDER FIRST - before payment processing
                placeOrderAction(this.getData())
                    .done(function (orderId) {
                        // Store order ID for later use
                        self.currentOrderId = orderId;
                        
                        // Now process payment with order ID
                        try {
                            var payload = self.buildPaymentPayload(customerInfo);
                            // Add order ID to payload metadata
                            payload.data.meta_data.order_id = orderId;
                            payload.data.meta_data.merchant_reference = orderId;
                            
                            new Klump(payload);
                        } catch (error) {
                            console.error('Error initializing Klump payment:', error);
                            self.isPlaceOrderActionAllowed(true);
                            self.showError("Failed to initialize payment. Please check your configuration and try again.");
                        }
                    })
                    .fail(function (response) {
                        self.handleOrderPlacementError(response);
                        self.isPlaceOrderActionAllowed(true);
                    });

                // try {
                //     // Build and execute payment
                //     var payload = this.buildPaymentPayload(customerInfo);
                //     new Klump(payload);
                // } catch (error) {
                //     console.error('Error initializing Klump payment:', error);
                //     this.isPlaceOrderActionAllowed(true);
                //     this.showError("Failed to initialize payment. Please check your configuration and try again.");
                // }
            }
        });
    }
);
