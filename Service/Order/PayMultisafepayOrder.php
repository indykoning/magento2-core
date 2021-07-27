<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is provided with Magento in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Copyright © 2021 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 *
 */

declare(strict_types=1);

namespace MultiSafepay\ConnectCore\Service\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use MultiSafepay\ConnectCore\Logger\Logger;
use MultiSafepay\ConnectCore\Util\CaptureUtil;
use MultiSafepay\ConnectCore\Config\Config;
use MultiSafepay\ConnectCore\Util\OrderStatusUtil;
use Magento\Sales\Api\OrderRepositoryInterface;

class PayMultisafepayOrder
{
    public const INVOICE_CREATE_AFTER_PARAM_NAME = 'multisafepay_create_inovice_after';

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $orderPaymentRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var CaptureUtil
     */
    private $captureUtil;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var OrderStatusUtil
     */
    private $orderStatusUtil;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * PayMultisafepayOrder constructor.
     *
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param CaptureUtil $captureUtil
     * @param Config $config
     * @param Logger $logger
     * @param OrderStatusUtil $orderStatusUtil
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        TransactionRepositoryInterface $transactionRepository,
        CaptureUtil $captureUtil,
        Config $config,
        Logger $logger,
        OrderStatusUtil $orderStatusUtil,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->captureUtil = $captureUtil;
        $this->config = $config;
        $this->logger = $logger;
        $this->orderStatusUtil = $orderStatusUtil;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param array $transaction
     */
    public function execute(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        array $transaction
    ): void {
        if ($order->canInvoice()) {
            $invoiceAmount = $order->getBaseTotalDue();
            $orderId = $order->getIncrementId();

            if (!$this->captureUtil->isCaptureManualTransaction($transaction)) {
                $isCreateOrderAutomatically = $this->config->isCreateOrderInvoiceAutomatically($order->getStoreId());
                $payment->setTransactionId($transaction['transaction_id'] ?? '')
                    ->setAdditionalInformation(
                        [
                            PaymentTransaction::RAW_DETAILS => (array)$payment->getAdditionalInformation(),
                            self::INVOICE_CREATE_AFTER_PARAM_NAME => !$isCreateOrderAutomatically,
                        ]
                    )->setShouldCloseParentTransaction(false)
                    ->setIsTransactionClosed(0)
                    ->setIsTransactionPending(false);

                $this->createInvoice($isCreateOrderAutomatically, $payment, $invoiceAmount, $orderId);

                $this->logger->logInfoForOrder($orderId, 'Invoice created', Logger::DEBUG);
                $payment->setParentTransactionId($transaction['transaction_id'] ?? '');
                $payment->setIsTransactionApproved(true);
                $this->orderPaymentRepository->save($payment);
                $this->logger->logInfoForOrder($orderId, 'Payment saved', Logger::DEBUG);
                $paymentTransaction = $payment->addTransaction(
                    PaymentTransaction::TYPE_CAPTURE,
                    null,
                    true
                );

                if ($paymentTransaction !== null) {
                    $paymentTransaction->setParentTxnId($transaction['transaction_id'] ?? '');
                }

                $paymentTransaction->setIsClosed(1);
                $this->transactionRepository->save($paymentTransaction);
                $this->logger->logInfoForOrder($orderId, 'Transaction saved', Logger::DEBUG);

                if (!$isCreateOrderAutomatically) {
                    $order->addCommentToStatusHistory(
                        __(
                            'Captured amount %1 by MultiSafepay. Transaction ID: "%2"',
                            $order->getBaseCurrency()->formatTxt($invoiceAmount),
                            $paymentTransaction->getTxnId()
                        )
                    );
                }
            }

            // Set order processing
            $status = $this->orderStatusUtil->getProcessingStatus($order);
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($status);
            $this->orderRepository->save($order);
            $this->logger->logInfoForOrder(
                $order->getIncrementId(),
                'Order status has been changed to: ' . $status,
                Logger::DEBUG
            );
        }
    }

    /**
     * @param bool $isCreateOrderAutomatically
     * @param OrderPaymentInterface $payment
     * @param float $captureAmount
     * @param string $orderId
     */
    private function createInvoice(
        bool $isCreateOrderAutomatically,
        OrderPaymentInterface $payment,
        float $captureAmount,
        string $orderId
    ): void {
        if ($isCreateOrderAutomatically) {
            $payment->registerCaptureNotification($captureAmount, true);
            $this->logger->logInfoForOrder($orderId, 'Invoice created', Logger::DEBUG);

            return;
        }

        $this->logger->logInfoForOrder(
            $orderId,
            'Invoice creation process was skipped by selected setting.',
            Logger::DEBUG
        );
    }
}
