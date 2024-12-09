<?php

namespace Klump\Payment\Observer;

use Klump\Payment\Helpers\SyncHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveAfter implements ObserverInterface
{
    protected $syncHelper;
    protected $productRepository;

    public function __construct(
        SyncHelper                 $syncHelper,
        ProductRepositoryInterface $productRepository
    ) {
        $this->syncHelper        = $syncHelper;
        $this->productRepository = $productRepository;
    }

    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        $data    = [];

        if ($product->getTypeId() == Configurable::TYPE_CODE) {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($children as $child) {
                $data[] = [
                    'id'    => $child->getId(),
                    'name'  => $child->getName(),
                    'price' => $child->getPrice(),
                    // Add other necessary child product attributes
                ];
            }
        } else {
            $data[] = [
                'id'    => $product->getId(),
                'name'  => $product->getName(),
                'price' => $product->getPrice(),
                // Add other necessary product attributes
            ];
        }

        $this->syncHelper->syncProducts([$data]);
    }
}
