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

namespace MultiSafepay\ConnectCore\Model\Api\Builder\OrderRequestBuilder;

use Magento\Payment\Gateway\Config\Config;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use MultiSafepay\Api\Transactions\OrderRequest;

class TransactionTypeBuilder implements OrderRequestBuilderInterface
{
    private const DEFAULT_TRANSACTION_TYPE = 'redirect';

    /**
     * @var Config
     */
    private $config;

    /**
     * SecondsActiveBuilder constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param OrderRequest $orderRequest
     * @return void
     */
    public function build(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        OrderRequest $orderRequest
    ): void {
        $transactionType = (string)$this->config->getValue('transaction_type');
        if (!$transactionType) {
            /*
             * @todo: put here default transaction type for a generic gateways methods
             */
            $transactionType = $payment->getAdditionalInformation()['transaction_type']
                               ?? self::DEFAULT_TRANSACTION_TYPE;
        }

        $orderRequest->addType($transactionType);
    }
}
