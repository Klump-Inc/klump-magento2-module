<?php

namespace Klump\Payment\Controller\Payment;

use Klump\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Request\Http;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;

class Webhook extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $request;
    protected $logger;
    protected $orderRepository;
    protected $paymentHelper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Http $request,
        LoggerInterface $logger,
        OrderRepository $orderRepository,
        PaymentHelper $paymentHelper,
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->paymentHelper = $paymentHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $data = $this->getRequest()->getContent();

        // Fetch the X-Klump-Signature header
        $signature = $this->getRequest()->getHeader('X-Klump-Signature');

        // Verify the received X-Klump-Signature
        if (!$this->verifySignature($data, $signature)) {
            $this->logger->error('Invalid webhook signature');
            return $resultJson->setData(['success' => false, 'message' => 'Invalid signature']);
        }

        try {
            $webhookData = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data');
            }

            $orderId = $webhookData['order_id'] ?? null;
            $transactionStatus = $webhookData['status'] ?? null;

            if (!$orderId || !$transactionStatus) {
                throw new \Exception('Missing order_id or status in webhook data');
            }

            // Load the order by incremental id
            $order = $this->orderRepository->get($orderId);

            if (!$order->getId()) {
                throw new \Exception('Order not found');
            }

            // Update the order status
            $order->setStatus($transactionStatus);
            $this->orderRepository->save($order);

            // Log the webhook data
            $this->logger->info('Webhook received', $webhookData);

            return $resultJson->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing error: ' . $e->getMessage());
            return $resultJson->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Verify the webhook signature.
     *
     * @param string $data
     * @param string $signature
     * @return bool
     */
    protected function verifySignature(string $data, ?string $signature): bool
    {
        $secret = $this->paymentHelper->getSecretKey();
        $this->logger->debug('Webhook $secret: ' . $secret);
        if (!$secret) return false;
        $expectedSignature = hash_hmac('sha256', $data, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
