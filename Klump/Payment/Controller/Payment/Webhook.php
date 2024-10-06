<?php

namespace Klump\Payment\Controller\Payment;

//use Klump\Payment\Helper\Data as PaymentHelper;
use Klump\Payment\Model\Ui\ConfigProvider;
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
//    protected $paymentHelper;
    protected $orderInterface;
    protected $orderSender;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Http $request,
        LoggerInterface $logger,
        ConfigProvider $configProvider,
        OrderRepository $orderRepository,
        OrderInterface $orderInterface,
//        PaymentHelper $paymentHelper,
        OrderSender $orderSender
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->logger = $logger;
        $this->configProvider = $configProvider;
        $this->orderRepository = $orderRepository;
        $this->orderInterface = $orderInterface;
//        $this->paymentHelper = $paymentHelper;
        $this->orderSender = $orderSender;
        parent::__construct($context);
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
            $webhookData = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data');
            }

            // @todo call Klump tnx api to verify transaction status

            $this->logger->info('webhook data', ['payload' => $webhookData]);

            if ($webhookData['event'] !== 'klump.payment.transaction.successful') {
                return $resultJson->setData(['success' => false, 'message' => 'Invalid event']);
            }

            $data = $webhookData['data'];

            $quoteId = $data['meta_data']['quote_id'] ?? null;

            if (!$quoteId) {
                return $resultJson->setData(['success' => false, 'message' => 'Missing quote_id in webhook data']);
            }

            $this->logger->info('order data', ['quoteId' => $quoteId]);

            $order = $this->orderInterface->loadByIncrementId($quoteId);

            if (!$order->getId()) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $searchCriteriaBuilder = $objectManager->create('Magento\Framework\Api\SearchCriteriaBuilder');
                $searchCriteria = $searchCriteriaBuilder->addFilter('quote_id', $quoteId, 'eq')->create();
                $items = $this->orderRepository->getList($searchCriteria);
                if($items->getTotalCount() == 1){
                    $order = $items->getFirstItem();
                }
            }

            if (!$order->getId()) {
                throw new \Exception('Order not found');
            }

            if ($order->getStatus() == "pending") {
                // sets the status to processing since payment has been received
                $order->setState(Order::STATE_PROCESSING)
                    ->addStatusToHistory(Order::STATE_PROCESSING, __("Klump BNPL Payment Verified and Order is being processed"), true)
                    ->setCanSendNewEmailFlag(true)
                    ->setCustomerNoteNotify(true);
                $this->orderRepository->save($order);

                $this->orderSender->send($order, true);
            }

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
        $secret = $this->configProvider->getSecretKey();
//        $this->paymentHelper->getSecretKey();
        if (!$secret) return false;
        $expectedSignature = hash_hmac('sha512', $data, $secret);
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
