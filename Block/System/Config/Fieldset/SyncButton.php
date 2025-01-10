<?php

namespace Klump\Payment\Block\System\Config\Fieldset;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SyncButton extends Field
{
    protected $_template = 'Klump_Payment::system/config/button.phtml';
    protected $scopeConfig;

    const XML_PATH_ENABLED_SYNC = 'payment/bnpl/enable_products_sync';

    public function __construct(
        Context              $context,
        ScopeConfigInterface $scopeConfig,
        array                $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        // Check if product sync is enabled
        if ($this->isProductSyncEnabled()) {
            return $this->_toHtml();
        }
        return ''; // Return empty string if sync is not enabled
    }

    public function getButtonHtml(): string
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setType('button');
        $button->setClass('action-primary');
        $button->setLabel(__('Sync All Products Now'));
        $button->setOnClick("setLocation('" . $this->getSyncUrl() . "')");

        return $button->toHtml();
    }

    public function getSyncUrl(): string
    {
        return $this->getUrl('klump/products/syncdata');
    }

    protected function isProductSyncEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED_SYNC,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
