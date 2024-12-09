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
        // Example: Extract product data from order
        foreach ($order->getAllVisibleItems() as $item) {
            $productId   = $item->getProductId();
            $productType = $item->getProductType();

            if ($productType === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                // It's a variation of a configurable product
                // Additional logic to manage specifics, if needed
                $childItems = $item->getChildrenItems();
                foreach ($childItems as $child) {
                    $data[] = [
                        'id'           => $child->getProductId(),
                        'variation_id' => $child->getId(),
                        'name'         => $child->getName(),
                        'sku'          => $child->getSku(),
                        'price'        => $child->getPrice(),
                        'quantity'     => $child->getQtyOrdered(),
                    ];
                }
            } else {
                $data[] = [
                    'id'           => $productId,
                    'variation_id' => null,
                    'name'         => $item->getName(),
                    'sku'          => $item->getSku(),
                    'price'        => $item->getPrice(),
                    'quantity'     => $item->getQtyOrdered(),
                ];
            }
        }

        if (!$data) {
            return;
        }

        $this->syncHelper->syncProducts($data);
    }
}
