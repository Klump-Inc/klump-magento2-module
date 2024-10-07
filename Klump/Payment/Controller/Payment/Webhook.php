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

            if ($webhookData['event'] !== 'klump.payment.transaction.successful') {
                return $resultJson->setData(['success' => false, 'message' => 'Invalid event']);
            }

            $order = $this->getOrder($webhookData);

            $this->logger->info('Order status', ['status' => $order->getStatus(), 'order_id' => $order->getId()]);

            if ($order->getStatus() == "pending") {
                // sets the status to processing since payment has been received
                $order->setState(Order::STATE_PROCESSING)
                    ->addStatusToHistory(Order::STATE_PROCESSING, __("Klump BNPL Payment Verified and Order is being processed"), true)
                    ->setCanSendNewEmailFlag(true)
                    ->setCustomerNoteNotify(true);
                $this->orderRepository->save($order);

                $this->orderSender->send($order, true);
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
        if (!$secret) return false;
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
            throw new \Exception('Invalid JSON data');
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
