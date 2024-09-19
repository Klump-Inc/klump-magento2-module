<?php

namespace Klump\Bnpl\Model\Payment;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;

class KlumpPayment implements MethodInterface
{
    protected string $_code = 'klump_bnpl';
    protected $_storeId;

    public function authorize(InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $currency = $order->getOrderCurrencyCode();

        // Check if the currency is allowed
        if ($currency !== 'NGN') {
            throw new LocalizedException(__('Currency not supported.'));
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * @return string
     */
    public function getFormBlockType()
    {
        return 'Klump\Bnpl\Block\Form\KlumpPayment';
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return __('Klump Buy Now Pay Later');
    }

    /**
     * @param $storeId
     * @return void
     */
    public function setStore($storeId)
    {
        $this->_storeId = $storeId;
    }

    /**
     * @return int
     */
    public function getStore()
    {
        return $this->_storeId;
    }

    /**
     * @return bool
     */
    public function canOrder()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canAuthorize()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canCapture()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canCapturePartial()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canCaptureOnce()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canRefund()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canRefundPartialPerInvoice()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canVoid()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canUseInternal()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canUseCheckout()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canEdit()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canFetchTransactionInfo()
    {
        return true;
    }

    /**
     * @param InfoInterface $payment
     * @param $transactionId
     * @return array
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {
        return [
            'transaction_id' => $transactionId,
            'status' => 'success',
            'amount' => $payment->getAmountOrdered()
        ];
    }

    /**
     * @return bool
     */
    public function isGateway()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isOffline()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return false;
    }

    /**
     * @param $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        return true;
    }

    /**
     * @param $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        return $currencyCode == 'NGN';
    }

    /**
     * @return string
     */
    public function getInfoBlockType()
    {
        return 'Klump\Bnpl\Block\Info\KlumpPayment';
    }

    /**
     * @return InfoInterface
     */
    public function getInfoInstance()
    {
        return $this->_infoInstance;
    }

    /**
     * @param InfoInterface $info
     * @return void
     */
    public function setInfoInstance(InfoInterface $info)
    {
        $this->_infoInstance = $info;
    }

    /**
     * @return $this
     */
    public function validate()
    {
        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param $amount
     * @return $this
     */
    public function order(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param $amount
     * @return $this
     */
    public function capture(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param $amount
     * @return $this
     */
    public function refund(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @return $this
     */
    public function cancel(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @return $this
     */
    public function void(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function canReviewPayment()
    {
        return true;
    }

    /**
     * @param InfoInterface $payment
     * @return bool
     */
    public function acceptPayment(InfoInterface $payment)
    {
        return true;
    }

    /**
     * @param InfoInterface $payment
     * @return bool
     */
    public function denyPayment(InfoInterface $payment)
    {
        return false;
    }

    /**
     * @param $field
     * @param $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        return 'config_value';
    }

    /**
     * @param DataObject $data
     * @return $this
     */
    public function assignData(DataObject $data)
    {
        return $this;
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        return true;
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return true;
    }

    /**
     * @param $paymentAction
     * @param $stateObject
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return 'authorize';
    }
}
