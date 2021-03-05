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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use MultiSafepay\Api\Transactions\OrderRequest;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\ShoppingCart;
use MultiSafepay\ConnectCore\Model\Api\Builder\OrderRequestBuilder\ShoppingCartBuilder\ShoppingCartBuilderInterface;
use MultiSafepay\ConnectCore\Util\CurrencyUtil;

class ShoppingCartBuilder implements OrderRequestBuilderInterface
{
    /**
     * @var array
     */
    protected $shoppingCartBuilders;

    /**
     * @var CurrencyUtil
     */
    private $currencyUtil;

    /**
     * ShoppingCartBuilder constructor.
     *
     * @param CurrencyUtil $currencyUtil
     * @param ShoppingCartBuilderInterface[] $shoppingCartBuilders
     */
    public function __construct(
        CurrencyUtil $currencyUtil,
        array $shoppingCartBuilders
    ) {
        $this->currencyUtil = $currencyUtil;
        $this->shoppingCartBuilders = $shoppingCartBuilders;
    }

    /**
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param OrderRequest $orderRequest
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function build(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        OrderRequest $orderRequest
    ): void {
        $items = [];

        $currency = $this->currencyUtil->getCurrencyCode($order);

        foreach ($this->shoppingCartBuilders as $shoppingCartBuilder) {
            $items[] = $shoppingCartBuilder->build($order, $currency);
        }

        $orderRequest->addShoppingCart(new ShoppingCart(array_merge([], ...$items)));
    }
}
