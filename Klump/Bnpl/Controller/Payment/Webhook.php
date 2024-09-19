<?php

namespace Klump\Bnpl\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;

class Webhook extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $request;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Http $request,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->logger->info('Webhook received');
        if ($this->request->isPost()) {
            $postData = $this->request->getPostValue();
            // Process the POST data here
            $result = $this->resultJsonFactory->create();
            $data = ['success' => true, 'message' => 'Webhook received', 'data' => $postData];
            return $result->setData($data);
        } else {
            $result = $this->resultJsonFactory->create();
            $data = ['success' => false, 'message' => 'Invalid request method'];
            return $result->setData($data);
        }

        // Process the webhook data
        $this->logger->info('Webhook received');
        $result = $this->resultJsonFactory->create();
        $data = ['success' => true, 'message' => 'Webhook received'];
        return $result->setData($data);

        $resultJson = $this->resultJsonFactory->create();
        $data = $this->getRequest()->getContent();

        try {
            $webhookData = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data');
            }

            // Process the webhook data
            $this->logger->info('Webhook received', $webhookData);

            // Implement your custom logic here

            return $resultJson->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing error: ' . $e->getMessage());
            return $resultJson->setData(['success' => false, 'message' => $e->getMessage()]);
        }
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
