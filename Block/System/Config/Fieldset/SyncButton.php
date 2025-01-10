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
    const XML_PATH_SECRET_KEY = 'payment/bnpl/secret_key';
    const XML_PATH_PUBLIC_KEY = 'payment/bnpl/public_key';

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
        $secretKey = $this->scopeConfig->getValue(self::XML_PATH_SECRET_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $publicKey = $this->scopeConfig->getValue(self::XML_PATH_PUBLIC_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED_SYNC, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        return !empty($secretKey) && !empty($publicKey) && !empty($enabled);
    }
}
