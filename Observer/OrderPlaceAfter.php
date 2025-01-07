<?php

namespace Klump\Payment\Observer;

use Klump\Payment\Helpers\SyncHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderPlaceAfter implements ObserverInterface
{
    protected $syncHelper;

    public function __construct(SyncHelper $syncHelper)
    {
        $this->syncHelper = $syncHelper;
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $data  = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $productId   = $item->getProductId();
            $productType = $item->getProductType();

            if ($productType === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                $childItems = $item->getChildrenItems();
                foreach ($childItems as $child) {
                    $stockItem = $child->getExtensionAttributes()->getStockItem();
                    $quantity  = $stockItem ? $stockItem->getQty() : 0;
                    if ($quantity === null) {
                        $quantity = $child->getIsInStock() ? 1 : 0;
                    }

                    $data[] = [
                        'name'         => $child->getName(),
                        'product_id'   => $productId,
                        'variant_id'   => $child->getId(),
                        'variant_name' => $child->getName(),
                        'quantity'     => $quantity,
                        'is_published' => $item->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                        'price'        => $child->getPrice(),
                        'old_price'    => $child->getPrice() !== $child->getSpecialPrice() ? $child->getSpecialPrice() : 0,
                        'description'  => $item->getDescription(),
                        'sku'          => $child->getSku(),
                    ];
                }
            } else {
                $stockItem = $item->getExtensionAttributes()->getStockItem();
                $quantity  = $stockItem ? $stockItem->getQty() : 0;
                if ($quantity === null) {
                    $quantity = $item->getIsInStock() ? 1 : 0;
                }
                $data[] = [
                    'name'         => $item->getName(),
                    'product_id'   => $productId,
                    'variant_id'   => null,
                    'variant_name' => null,
                    'quantity'     => $quantity,
                    'is_published' => $item->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                    'price'        => $item->getPrice(),
                    'old_price'    => $child->getPrice() !== $child->getSpecialPrice() ? $child->getSpecialPrice() : 0,
                    'description'  => $item->getDescription(),
                    'sku'          => $item->getSku(),
                ];
            }
        }

        if (!$data) {
            return;
        }

        $this->syncHelper->syncProducts($data);
    }
}
