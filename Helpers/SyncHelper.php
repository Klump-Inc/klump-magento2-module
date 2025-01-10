<?php

namespace Klump\Payment\Helpers;

use GuzzleHttp\Client;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;

class SyncHelper extends AbstractHelper
{
    protected $logger;
    protected $httpClient;
    protected $scopeConfig;
    protected $imageHelper;
    protected $categoryFactory;

    const XML_PATH_ENABLED_SYNC = 'payment/bnpl/enable_products_sync';
    const SYNC_URL = 'https://api.useklump.com/v1/commerce/products/sync';

    public function __construct(
        Context         $context,
        LoggerInterface $logger,
        Client          $client,
        ImageHelper       $imageHelper,
        CategoryFactory   $categoryFactory
    ) {
        parent::__construct($context);
        $this->logger      = $logger;
        $this->httpClient  = $client;
        $this->scopeConfig = $context->getScopeConfig();
        $this->imageHelper              = $imageHelper;
        $this->categoryFactory          = $categoryFactory;
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
            $this->httpClient->post(self::SYNC_URL, [
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

    /**
     * Computes item details for a product or a child product.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\Product|null $child
     * @param \Magento\Catalog\Helper\Image|null $imageHelper
     * @return array
     */
    public function computeItemDetails($product, $child = null)
    {
        $oldPrice = $child
            ? ($child->getPrice() !== $child->getSpecialPrice() ? $child->getSpecialPrice() : 0)
            : ($product->getPrice() !== $product->getSpecialPrice() ? $product->getSpecialPrice() : 0);

        $item = [
            'name'         => $product->getName(),
            'product_id'   => $product->getId(),
            'variant_id'   => $child?->getId(),
            'variant_name' => $child?->getName(),
            'is_published' => $product->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            'price'        => $child ? $child->getPrice() : $product->getPrice(),
            'old_price'    => $oldPrice,
            'description'  => $product->getDescription(),
            'sku'          => $child ? $child->getSku() : $product->getSku(),
            'image' =>      $this->imageHelper->init($child ?? $product, 'product_page_image_small')->getUrl(),
            'sub_category' => $this->getProductCategory($product),
            'category'     => $this->getProductCategory($product, true),
        ];

        $stockItem = $child
            ? $child->getExtensionAttributes()->getStockItem()
            : $product->getExtensionAttributes()->getStockItem();
        $quantity  = $stockItem ? $stockItem->getQty() : 0;
        if ($quantity === null) {
            $quantity = ($child ? $child->getIsInStock() : $product->getIsInStock()) ? 1 : 0;
        }
        $item['quantity'] = is_numeric($quantity) && $quantity >= 0 ? (int)$quantity : 0;

        return $item;
    }

    protected function getProductCategory($product, $topCategory = false)
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $categories = $this->categoryFactory->create()->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $categoryIds]);

        if ($topCategory) {
            $categoryNames = [];
            foreach ($categories as $category) {
                if ($category->getLevel() == 2) {
                    $categoryNames[] = $category->getName();
                }
            }
            return implode(', ', $categoryNames);
        }

        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[] = $category->getName();
        }

        return implode(', ', $categoryNames);
    }
}
