<?php

namespace Klump\Payment\Observer;

use Klump\Payment\Helpers\SyncHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductDeleteAfter implements ObserverInterface
{
    protected $syncHelper;

    public function __construct(SyncHelper $syncHelper)
    {
        $this->syncHelper = $syncHelper;
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getDataObject();

        $data = [];

        if ($product->getTypeId() == Configurable::TYPE_CODE) {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($children as $child) {
                $data[] = [
                    'name'         => $child->getName(),
                    'product_id'   => $product->getId(),
                    'variant_id'   => $child->getId(),
                    'variant_name' => $child->getName(),
                    'quantity'     => 0,
                    'image'        => null,
                    'is_published' => false,
                    'price'        => $child->getPrice(),
                    'sku'          => $child->getSku(),
                ];
            }
        } else {
            $data[] = [
                'name'         => $product->getName(),
                'product_id'   => $product->getId(),
                'variant_id'   => null,
                'variant_name' => null,
                'quantity'     => 0,
                'image'        => null,
                'is_published' => false,
                'price'        => $product->getPrice(),
                'sku'          => $product->getSku(),
            ];
        }

        if ($data) {
            $this->syncHelper->syncProducts($data);
        }
    }
}
