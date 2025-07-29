<?php

namespace Klump\Payment\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class BnplPayment extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'bnpl';
    protected $_code = self::CODE;

    protected $_isOffline = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = true;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }

    // Add this method if it does not exist
    public function authorize(InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new LocalizedException(__('The authorize action is not available.'));
        }

        // Set order to pending payment status initially
        $order = $payment->getOrder();
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus('pending_payment');
        $order->addStatusToHistory(
            'pending_payment',
            __('Order created. Awaiting Klump payment confirmation.'),
            false
        );

        return $this;
    }

    public function canAuthorize()
    {
        return true;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return false; // Stay on checkout page for Klump modal
    }
}
