<?php

namespace Klump\Payment\Model\Payment;

class BnplPayment extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'bnpl';
    protected $_code = self::CODE;

    protected $_isOffline = false;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }

//    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
//    {
//        $this->logger->debug(['Authorization process started for amount: ' . $amount]);
//
//        return $this;
//    }
}
