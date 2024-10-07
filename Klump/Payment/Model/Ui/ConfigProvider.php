<?php

namespace Klump\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\Store as Store;

class ConfigProvider implements ConfigProviderInterface
{
    protected $checkoutSession;
    protected $method;
    protected $store;

    public function __construct(
        CheckoutSession $checkoutSession,
        PaymentHelper $paymentHelper,
        Store $store
    )
    {
        $this->method = $paymentHelper->getMethodInstance(\Klump\Payment\Model\BnplPayment::CODE);
        $this->checkoutSession = $checkoutSession;
        $this->store = $store;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                \Klump\Payment\Model\BnplPayment::CODE => [
                    'public_key' => $this->getPublicKey(),
                ]
            ],
//            'quoteData' => [
//                'entity_id' => $this->checkoutSession->getQuote()->getId()
//            ],
        ];
    }

    public function getStore() {
        return $this->store;
    }

    /**
     * Get secret key for webhook process
     *
     * @return string
     */
    public function getSecretKey()
    {
        return $this->method->getConfigData('test_mode') ? $this->method->getConfigData('test_secret_key') : $this->method->getConfigData('secret_key');
    }

    public function getPublicKey(){
        return $this->method->getConfigData('test_mode') ? $this->method->getConfigData('test_public_key') : $this->method->getConfigData('public_key');
    }
}
