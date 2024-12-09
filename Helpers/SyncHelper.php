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

    const XML_PATH_ENABLED_SYNC = 'payment/bnpl/enabled_sync';

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Client $client
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->httpClient = $client;
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

        try {
            $this->httpClient->post('https://rarely-in-sunbeam.ngrok-free.app/v1/sync', [
                'json' => $productData,
            ]);
            $this->logger->info('Product synced: ' . count($productData));
        } catch (\Exception $e) {
            $this->logger->error('Product sync failed: ' . $e->getMessage());
        }
    }
}
