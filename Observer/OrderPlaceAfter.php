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

        foreach ($order->getAllVisibleItems() as $product) {
            $productType = $product->getProductType();

            if ($productType === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                $childItems = $product->getChildrenItems();
                foreach ($childItems as $child) {
                    $data[] = $this->syncHelper->computeItemDetails($product, $child);
                }
            } else {
                $data[] = $this->syncHelper->computeItemDetails($product);
            }
        }

        if (!$data) {
            return;
        }

        $this->syncHelper->syncProducts($data);
    }
}
