<?php

namespace Klump\Payment\Helpers;

use GuzzleHttp\Client;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;

class SyncHelper extends AbstractHelper
{
    protected $logger;
    protected $httpClient;
    protected $scopeConfig;

    const XML_PATH_ENABLED_SYNC = 'payment/bnpl/enable_products_sync';

    public function __construct(
        Context         $context,
        LoggerInterface $logger,
        Client          $client,
    ) {
        parent::__construct($context);
        $this->logger      = $logger;
        $this->httpClient  = $client;
        $this->scopeConfig = $context->getScopeConfig();
    }

    public function isSyncEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED_SYNC,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function syncProducts(array $productData): void
    {
        if (!$this->isSyncEnabled()) {
            return;
        }

        $secretKey = $this->scopeConfig->getValue('payment/bnpl/secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $publicKey = $this->scopeConfig->getValue('payment/bnpl/public_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (empty($secretKey) || empty($publicKey)) {
            $this->logger->error('Credentials for product sync not set');
            return;
        }

        try {
            $this->httpClient->post('https://api.useklump.com/v1/commerce/products/sync', [
                'json'    => $productData,
                'headers' => [
                    'Content-Type'       => 'application/json',
                    'X-Klump-Signature'  => hash_hmac('sha512', json_encode($productData), $secretKey),  // Generate HMAC signature
                    'X-Klump-Public-Key' => $publicKey,
                    'X-Plugin-Type'      => 'Magento 2',
                ],
            ]);
            $this->logger->info('Product synced: ' . count($productData));
        } catch (\Exception $e) {
            $this->logger->error('Product sync failed: ' . $e->getMessage());
        }
    }
}
