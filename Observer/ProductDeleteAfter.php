<?php

namespace Klump\Payment\Observer;

use Klump\Payment\Helpers\SyncHelper;
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
        $data = ['id' => $product->getId()];

        $this->syncHelper->syncProducts([$data]);
    }
}
