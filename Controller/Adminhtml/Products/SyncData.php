<?php

namespace Klump\Payment\Controller\Adminhtml\Products;

use Klump\Payment\Helpers\SyncHelper;
use Magento\Backend\App\Action;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Controller\ResultFactory;

class SyncData extends Action
{
    protected $syncHelper;
    protected $productCollectionFactory;
    protected $imageHelper;
    protected $categoryFactory;

    public function __construct(
        Action\Context    $context,
        SyncHelper        $syncHelper,
        CollectionFactory $productCollectionFactory,
        ImageHelper       $imageHelper,
        CategoryFactory   $categoryFactory
    ) {
        parent::__construct($context);
        $this->syncHelper               = $syncHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageHelper              = $imageHelper;
        $this->categoryFactory          = $categoryFactory;
    }

    public function execute()
    {
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToSelect(['name', 'price', 'status', 'sku', 'image', 'description']);
//        $productCollection->addAttributeToFilter('status', ['eq' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED]);

        $data = [];
        foreach ($productCollection as $product) {
            if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                // Handle configurable product
                $childProducts = $product->getTypeInstance()->getUsedProducts($product);
                foreach ($childProducts as $child) {
                    $data[] = $this->syncHelper->computeItemDetails($product, $child);
                }
            } elseif ($product->getTypeId() == \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE) {
                // Handle simple product
                $data[] = $this->syncHelper->computeItemDetails($product);
            }
        }

        if ($data) {
            $this->syncHelper->syncProducts($data);
        }

        $this->messageManager->addSuccessMessage(__('Product synchronization initiated successfully.'));
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('adminhtml/system_config/edit/section/payment');
        return $resultRedirect;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Klump_Payment::sync'); // Adjust your ACL resource
    }


}
