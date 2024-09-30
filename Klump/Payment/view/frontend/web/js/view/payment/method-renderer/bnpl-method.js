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

                // console.log('isPlaceOrderActionAllowed', this.isPlaceOrderActionAllowed())
                // console.log('get code and checked', this.getCode() === this.isChecked())
                // Additional init tasks can be done here
                return this;
            },

            loadExternalScript: function (url) {
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                document.head.appendChild(script);
            },

            // getCode: function () {
            //     return 'bnpl';
            // },

            isActive: function () {
                console.log('isChecked()', this.isChecked())
                return true;
            },

            /** Returns send check to info */
            // getMailingAddress: function() {
            //     return window.checkoutConfig.payment.bnpl.mailingAddress;
            // },

            placeOrder: function () {
                console.log('this is called, yearhu : placeOrder')
                if (this.validate()) {
                    this._super();
                }
                return true;
            },

            // placeOrder: function (data, event) {
            //     if (event) {
            //         event.preventDefault();
            //     }
            //     var self = this;
            //     if (this.validate() && additionalChecks()) { // Assuming 'additionalChecks' are required for your payment method
            //         this.isPlaceOrderActionAllowed(false);
            //         this.getPlaceOrderDeferredObject()
            //             .fail(
            //                 function () {
            //                     self.isPlaceOrderActionAllowed(true);
            //                 }
            //             ).done(
            //             function () {
            //                 self.afterPlaceOrder();
            //
            //                 // After Place Order, Open Klump Modal or Redirect to Klump
            //                 // var klumpInstance = new Klump({
            //                 //     apiKey: 'your-api-key',
            //                 //     environment: 'sandbox',
            //                 //     // Other required parameters
            //                 // });
            //                 // klumpInstance.openModal();
            //             }
            //         );
            //         return true;
            //     }
            //     return false;
            // },

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
                var handler = PaystackPop.setup({
                    key: paystackConfiguration.public_key,
                    email: paymentData.email,
                    amount: Math.ceil(quote.totals().grand_total * 100), // get order total from quote for an accurate... quote
                    phone: paymentData.telephone,
                    currency: checkoutConfig.totalsData.quote_currency_code,
                    metadata: {
                        quoteId: quoteId,
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
                    callback: function (response) {
                        fullScreenLoader.startLoader();
                        $.ajax({
                            method: "GET",
                            url: paystackConfiguration.api_url + "V1/paystack/verify/" + response.reference + "_-~-_" + quoteId
                        }).success(function (data) {
                            data = JSON.parse(data);
                            //JS PSTK-logger
                            $.ajax({
                                method: 'POST',
                                url: "https://plugin-tracker.paystackintegrations.com/log/charge_success",
                                data:{
                                    plugin_name: 'magento-2',
                                    transaction_reference: response.reference,
                                    public_key: paystackConfiguration.public_key
                                }
                            })
                            if (data.status) {
                                if (data.data.status === "success") {
                                    // redirect to success page after
                                    redirectOnSuccessAction.execute();
                                    return;
                                }
                            }

                            fullScreenLoader.stopLoader();

                            _this.isPlaceOrderActionAllowed(true);
                            _this.messageContainer.addErrorMessage({
                                message: "Error, please try again"
                            });
                        });
                    },
                    onClose: function(){
                        _this.redirectToCustomAction(paystackConfiguration.recreate_quote_url);
                    }
                });
                handler.openIframe();
            }
        });
    }
);
