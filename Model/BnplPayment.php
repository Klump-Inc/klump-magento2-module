<?php

namespace Klump\Payment\Model;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class BnplPayment extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'bnpl';
    protected $_code = self::CODE;

    protected $_isOffline = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = true;
    protected $_canOrder = true;
    protected $_isInitializeNeeded = true;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }

    /**
     * Initialize payment method
     * This method is called when _isInitializeNeeded = true
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * Override to ensure Magento uses the 'order' action
     */
    public function getConfigPaymentAction()
    {
        return 'order';
    }

    public function order(InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus('pending_payment');

        $order->setCanSendNewEmailFlag(false);
        $order->setEmailSent(true);

        $order->addStatusToHistory(
            'pending_payment',
            __('Order created. Awaiting Klump payment confirmation.'),
            false
        );

        return $this;
    }

    public function canOrder()
    {
        return true;
    }

    public function canAuthorize()
    {
        return false;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return false; // Stay on checkout page for Klump modal
    }
}
