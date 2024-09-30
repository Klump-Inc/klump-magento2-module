<?php

namespace Klump\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\Store as Store;

class ConfigProvider implements ConfigProviderInterface
{
    protected $method;
    protected $store;

    public function __construct(
        PaymentHelper $paymentHelper, Store $store
    )
    {
        $this->method = $paymentHelper->getMethodInstance(\Klump\Payment\Model\Payment\BnplPayment::CODE);
        $this->store = $store;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $publicKey = $this->method->getConfigData('live_public_key');
        if ($this->method->getConfigData('test_mode')) {
            $publicKey = $this->method->getConfigData('test_public_key');
        }

        return [
            'payment' => [
                \Klump\Payment\Model\Payment\BnplPayment::CODE => [
                    'public_key' => $publicKey,
                ]
            ]
        ];
    }

    public function getStore() {
        return $this->store;
    }

    /**
     * Get secret key for webhook process
     *
     * @return array
     */
    public function getSecretKeyArray(){
        $data = ["live" => $this->method->getConfigData('live_secret_key')];
        if ($this->method->getConfigData('test_mode')) {
            $data = ["test" => $this->method->getConfigData('test_secret_key')];
        }

        return $data;
    }

    public function getPublicKey(){
        $publicKey = $this->method->getConfigData('live_public_key');
        if ($this->method->getConfigData('test_mode')) {
            $publicKey = $this->method->getConfigData('test_public_key');
        }
        return $publicKey;
    }
}
