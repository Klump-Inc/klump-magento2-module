<?php
namespace Klump\Payment\Block\System\Config\Fieldset;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;

class AdPreview extends Template
{
    protected $registry;
    protected $paymentHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        PaymentHelper $paymentHelper,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->paymentHelper = $paymentHelper;
        parent::__construct($context, $data);
    }

    public function getCurrentProduct()
    {
        return $this->registry->registry('current_product');
    }

    public function isActive()
    {
        $method = $this->paymentHelper->getMethodInstance('bnpl');
        return $method->isAvailable();
    }

    public function getProductPrice()
    {
        $product = $this->getCurrentProduct();
        return $product ? $product->getFinalPrice() : 0;
    }

    public function getPublicKey()
    {
        $isTestMode = $this->_scopeConfig->getValue('payment/bnpl/test_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        if ($isTestMode) {
            return $this->_scopeConfig->getValue('payment/bnpl/test_public_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
        
        return $this->_scopeConfig->getValue('payment/bnpl/public_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getCurrentCurrency()
    {
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }
}
