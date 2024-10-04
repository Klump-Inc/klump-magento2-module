<?php

namespace Klump\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_SECRET_KEY = 'payment/bnpl/secret_key';
    const XML_PATH_TEST_SECRET_KEY = 'payment/bnpl/test_secret_key';
    const XML_PATH_TEST_MODE = 'payment/bnpl/test_mode';

    /**
     * Get secret key
     *
     * @param null|int $storeId
     * @return string
     */
    public function getSecretKey($storeId = null)
    {
        $isTestMode = $this->scopeConfig->isSetFlag(
            self::XML_PATH_TEST_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($isTestMode) {
            return $this->scopeConfig->getValue(
                self::XML_PATH_TEST_SECRET_KEY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return $this->scopeConfig->getValue(
            self::XML_PATH_SECRET_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
