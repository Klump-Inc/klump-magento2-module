<?php

namespace Klump\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;

class UpdateStatus extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $orderRepository;
    protected $orderSender;
    protected $logger;

    public function __construct(
        Context         $context,
        JsonFactory     $resultJsonFactory,
        OrderRepository $orderRepository,
        OrderSender     $orderSender,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderRepository   = $orderRepository;
        $this->orderSender       = $orderSender;
        $this->logger            = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $orderId       = $this->getRequest()->getParam('order_id');
            $transactionId = $this->getRequest()->getParam('transaction_id');

            if (!$orderId) {
                return $resultJson->setData(['success' => false, 'message' => 'Order ID is required']);
            }

            $order = $this->orderRepository->get($orderId);

            if ($order->getStatus() === 'pending_payment') {
                $order->setState(Order::STATE_PROCESSING)
                    ->setStatus('processing')
                    ->addStatusToHistory(
                        'processing',
                        __('Klump BNPL Payment completed successfully. Transaction ID: %1', $transactionId ?: 'N/A'),
                        true
                    )
                    ->setCanSendNewEmailFlag(true)
                    ->setCustomerNoteNotify(true);

                $this->orderRepository->save($order);
                $this->orderSender->send($order, true);

                $this->logger->info('Order status updated to processing', [
                    'order_id'       => $orderId,
                    'transaction_id' => $transactionId,
                ]);

                return $resultJson->setData(['success' => true, 'message' => 'Order status updated successfully']);
            } else {
                return $resultJson->setData(['success' => false, 'message' => 'Order is not in pending payment status']);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error updating order status: ' . $e->getMessage());
            return $resultJson->setData(['success' => false, 'message' => 'Error updating order status']);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
