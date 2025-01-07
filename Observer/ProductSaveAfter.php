<?php

namespace Klump\Payment\Observer;

use Klump\Payment\Helpers\SyncHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveAfter implements ObserverInterface
{
    protected $syncHelper;
    protected $productRepository;
    protected $imageHelper;

    public function __construct(
        SyncHelper                 $syncHelper,
        ProductRepositoryInterface $productRepository,
        ImageHelper                $imageHelper,
    ) {
        $this->syncHelper        = $syncHelper;
        $this->productRepository = $productRepository;
        $this->imageHelper       = $imageHelper;
    }

    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        $data    = [];

        if ($product->getTypeId() == Configurable::TYPE_CODE) {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($children as $child) {
                $stockItem = $child->getExtensionAttributes()->getStockItem();
                $quantity  = $stockItem ? $stockItem->getQty() : 0;
                if ($quantity === null) {
                    $quantity = $child->getIsInStock() ? 1 : 0;
                }

                $data[] = [
                    'name'         => $child->getName(),
                    'product_id'   => $product->getId(),
                    'variant_id'   => $child->getId(),
                    'variant_name' => $child->getName(),
                    'quantity'     => $quantity,
                    'image'        => $this->imageHelper->init($child, 'product_page_image_small')->getUrl(),
                    'is_published' => $product->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                    'price'        => $child->getPrice(),
                    'old_price'    => $child->getPrice() !== $child->getSpecialPrice() ? $child->getSpecialPrice() : 0,
                    'description'  => $product->getDescription(),
                    'sku'          => $child->getSku(),
                ];
            }
        } else {
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            $quantity  = $stockItem ? $stockItem->getQty() : 0;
            if ($quantity === null) {
                $quantity = $product->getIsInStock() ? 1 : 0;
            }
            $data[] = [
                'name'         => $product->getName(),
                'product_id'   => $product->getId(),
                'variant_id'   => null,
                'variant_name' => null,
                'quantity'     => $quantity,
                'image'        => $this->imageHelper->init($product, 'product_page_image_small')->getUrl(),
                'is_published' => $product->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                'price'        => $product->getPrice(),
                'old_price'    => $product->getPrice() !== $product->getSpecialPrice() ? $product->getSpecialPrice() : 0,
                'description'  => $product->getDescription(),
                'sku'          => $product->getSku(),
            ];
        }

        $this->syncHelper->syncProducts($data);
    }
}
