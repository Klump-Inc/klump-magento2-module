<?php

namespace Klump\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class Recreate extends Action {
    protected $logger;
    protected $checkoutSession;

    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context);
    }

    public function execute() {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation("Payment failed or cancelled")->save();
        }

        $this->checkoutSession->restoreQuote();
        $this->_redirect('checkout', ['_fragment' => 'payment']);
    }
}
