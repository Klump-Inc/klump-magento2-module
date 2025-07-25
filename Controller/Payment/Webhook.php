<?php

namespace Klump\Payment\Controller\Payment;

use Klump\Payment\Model\Ui\ConfigProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Request\Http;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;

class Webhook extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $request;
    protected $logger;
    protected $configProvider;
    protected $orderRepository;
    protected $orderInterface;
    protected $orderSender;
    private $searchCriteriaBuilder;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Http $request,
        LoggerInterface $logger,
        ConfigProvider $configProvider,
        OrderRepository $orderRepository,
        OrderInterface $orderInterface,
        OrderSender $orderSender,
        SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->logger = $logger;
        $this->configProvider = $configProvider;
        $this->orderRepository = $orderRepository;
        $this->orderInterface = $orderInterface;
        $this->orderSender = $orderSender;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $data = $this->getRequest()->getContent();
        $signature = $this->getRequest()->getHeader('X-Klump-Signature'); // Fetch the X-Klump-Signature header

        // Verify the received X-Klump-Signature
        if (!$this->verifySignature($data, $signature)) {
            $this->logger->error('Invalid webhook signature');
            return $resultJson->setData(['success' => false, 'message' => 'Invalid signature']);
        }

        try {
            $webhookData = $this->validateWebhookData($data);
            $order = $this->getOrder($webhookData);

            $this->logger->info('Order status', ['status' => $order->getStatus(), 'order_id' => $order->getId()]);

            switch ($webhookData['event']) {
                case 'klump.payment.transaction.successful':
                    $this->logger->info('Klump Webhook: Processing successful payment', [
                        'order_id' => $order->getId(),
                        'before_status' => $order->getStatus()
                    ]);
                    if ($order->getStatus() == 'pending') {
                        $order->setState(Order::STATE_PROCESSING)
                            ->addStatusToHistory(Order::STATE_PROCESSING, __('Klump BNPL Payment Verified and Order is being processed'), true)
                            ->setCanSendNewEmailFlag(true)
                            ->setCustomerNoteNotify(true);
                        $this->orderRepository->save($order);
                        $this->orderSender->send($order, true);
                    } elseif ($order->getState() === Order::STATE_PROCESSING) {
                        // Add verification for already processing orders
                        $order->addCommentToStatusHistory(
                            'Payment verified by Klump webhook. Transaction ID: ' . $webhookData['data']['transaction_id'],
                            false,
                            false,
                        );
                    }
                    break;

                case 'klump.payment.transaction.failed':
                case 'klump.payment.transaction.abandoned':
                    $order->setState(Order::STATE_CANCELED)
                        ->addStatusToHistory(Order::STATE_CANCELED, __("Klump BNPL Payment Cancelled/Abandoned"), true)
                        ->addStatusToHistory(Order::STATE_CANCELED, __("Klump BNPL Payment Failed"), true)
                        ->setCanSendNewEmailFlag(false)
                        ->setCustomerNoteNotify(true);
                    $this->orderRepository->save($order);
                    break;

                default:
                    $this->logger->error('Klump Webhook: Unhandled event type', [
                        'event' => $webhookData['event'],
                        'available_data' => array_keys($webhookData)
                    ]);
                    return $resultJson->setData(['success' => false, 'message' => 'Unhandled event type']);
            }

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
     * @param string|null $signature
     * @return bool
     */
    protected function verifySignature(string $data, ?string $signature): bool
    {
        $secret = $this->configProvider->getSecretKey();

        if (!$secret) {
            $this->logger->error('Klump Webhook: Secret key not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha512', $data, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @param string $data
     * @return array
     * @throws \Exception
     */
    private function validateWebhookData(string $data): array
    {
        $webhookData = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Klump Webhook: JSON parsing error', [
                'json_error' => json_last_error_msg(),
                'json_error_code' => json_last_error(),
                'raw_data' => substr($data, 0, 500) // Log first 500 chars
            ]);
            throw new \Exception('Invalid JSON data: ' . json_last_error_msg());
        }
        return $webhookData;
    }

    /**
     * @param $webhookData
     * @return OrderInterface|null
     * @throws \Exception
     */
    private function getOrder($webhookData): ?OrderInterface
    {
        $order = null;
        if (isset($webhookData['data']['merchant_reference'])) {
            $order = $this->orderInterface->loadByIncrementId($webhookData['data']['merchant_reference']);
        }

        if (!$order && isset($webhookData['data']['meta_data']['quote_id'])) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('quote_id', $webhookData['data']['meta_data']['quote_id'], 'eq')
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria)->getItems();

            if (count($orders) === 1) {
                $order = reset($orders);
            }
        }

        if (!$order || !$order->getId()) {
            throw new \Exception('Missing or invalid order details supplied in webhook.');
        }

        return $order;
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
