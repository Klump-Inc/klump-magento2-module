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
            $item = [
                'name'         => $product->getName(),
                'variant_id'   => null,
                'variant_name' => null,
                'is_published' => $product->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                'description'  => $product->getDescription(),
                'product_id'   => $product->getId(),
                'sub_category' => $this->getProductCategory($product),
                'category'     => $this->getProductCategory($product, true),
            ];

            if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                // Handle configurable product
                $childProducts = $product->getTypeInstance()->getUsedProducts($product);
                foreach ($childProducts as $child) {
                    $stockItem = $child->getExtensionAttributes()->getStockItem();
                    $quantity  = $stockItem ? $stockItem->getQty() : 0;
                    if ($quantity === null) {
                        $quantity = $child->getIsInStock() ? 1 : 0;
                    }

                    $item['variant_id']   = $child->getId();
                    $item['variant_name'] = $child->getName();
                    $item['quantity']     = $quantity;
                    $item['sku']          = $child->getSku();
                    $item['price']        = $child->getPrice();
                    $item['old_price']    = $child->getPrice() !== $child->getSpecialPrice() ? $child->getSpecialPrice() : 0;
                    $item['image']        = $this->imageHelper->init($child, 'product_page_image_small')->getUrl();
                }
            } elseif ($product->getTypeId() == \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE) {
                // Handle simple product
                $stockItem = $product->getExtensionAttributes()->getStockItem();
                $quantity  = $stockItem ? $stockItem->getQty() : 0;
                if ($quantity === null) {
                    $quantity = $product->getIsInStock() ? 1 : 0;
                }

                $item['quantity']  = $quantity;
                $item['image']     = $this->imageHelper->init($product, 'product_page_image_small')->getUrl();
                $item['price']     = $product->getPrice();
                $item['old_price'] = $product->getPrice() !== $product->getSpecialPrice() ? $product->getSpecialPrice() : 0;
                $item['sku']       = $product->getSku();
            }

            $data[] = $item;
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

    protected function getProductCategory($product, $topCategory = false)
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $categories = $this->categoryFactory->create()->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $categoryIds]);

        if ($topCategory) {
            $categoryNames = [];
            foreach ($categories as $category) {
                if ($category->getLevel() == 2) {
                    $categoryNames[] = $category->getName();
                }
            }
            return implode(', ', $categoryNames);
        }

        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[] = $category->getName();
        }

        return implode(', ', $categoryNames);
    }
}
