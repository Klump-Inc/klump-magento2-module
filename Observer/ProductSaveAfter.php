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
                $data[] = $this->syncHelper->computeItemDetails($product, $child);
            }
        } else {
            $data[] = $this->syncHelper->computeItemDetails($product);
        }

        $this->syncHelper->syncProducts($data);
    }
}
