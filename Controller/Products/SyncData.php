<?php

namespace Klump\Payment\Controller\Products;

use Klump\Payment\Helpers\SyncHelper;
use Magento\Backend\App\Action;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Controller\ResultFactory;

class SyncData extends Action
{
    protected $syncHelper;
    protected $productCollectionFactory;

    public function __construct(
        Action\Context $context,
        SyncHelper $syncHelper,
        CollectionFactory $productCollectionFactory
    ) {
        parent::__construct($context);
        $this->syncHelper = $syncHelper;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function execute()
    {
        $productCollection = $this->productCollectionFactory->create();
        $data = [];
        foreach ($productCollection as $product) {
            $data[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                // Add other necessary product attributes
            ];
        }

        if ($data) {
            $this->syncHelper->syncProducts($data);
        }

        $this->messageManager->addSuccessMessage(__('Product synchronization completed.'));
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('adminhtml/system_config/edit/section/payment');
        return $resultRedirect;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Klump_Payment::sync'); // Adjust your ACL resource
    }
}
