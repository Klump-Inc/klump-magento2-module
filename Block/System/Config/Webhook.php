<?php

namespace Klump\Payment\Block\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class Webhook extends Field
{
    protected $storeManager;
    protected $urlBuilder;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        StoreManagerInterface                   $storeManager,
        UrlInterface                            $urlBuilder,
        array                                   $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder   = $urlBuilder;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $baseUrl    = $this->storeManager->getStore()->getBaseUrl();
        $webhookUrl = $baseUrl . 'klump/payment/webhook';
        $html       = 'Login to your <a href="https://merchant.useklump.com/dashboard" target="_blank">' . __('merchant dashboard') . '</a> and enter the webhook url below:<br><br>'
            . "<strong style='color:#d70a0a;'>$webhookUrl</strong>";

        return $html;
    }
}
