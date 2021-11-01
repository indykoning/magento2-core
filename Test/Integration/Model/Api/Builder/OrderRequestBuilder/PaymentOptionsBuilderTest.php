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

namespace MultiSafepay\ConnectCore\Test\Integration\Model\Api\Builder\OrderRequestBuilder;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use MultiSafepay\Api\Transactions\OrderRequest;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\PaymentOptions;
use MultiSafepay\ConnectCore\Logger\Logger;
use MultiSafepay\ConnectCore\Model\Api\Builder\OrderRequestBuilder\PaymentOptionsBuilder;
use MultiSafepay\ConnectCore\Model\Api\Builder\OrderRequestBuilder\PluginDataBuilder;
use MultiSafepay\ConnectCore\Model\SecureToken;
use MultiSafepay\ConnectCore\Service\Invoice\CreateInvoiceAfterShipment;
use MultiSafepay\ConnectCore\Test\Integration\Payment\AbstractTransactionTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MultiSafepay\ConnectCore\Model\Ui\Giftcard\EdenredGiftcardConfigProvider;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentOptionsBuilderTest extends AbstractTransactionTestCase
{
    /**
     * @var PaymentOptionsBuilder
     */
    private $paymentOptionsBuilder;

    /**
     * @var PluginDataBuilder
     */
    private $pluginDetailsBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->paymentOptionsBuilder = $this->getObjectManager()->create(PaymentOptionsBuilder::class);
        $this->pluginDetailsBuilder = $this->getObjectManager()->create(PluginDataBuilder::class);
    }

    /**
     * @magentoDataFixture   Magento/Sales/_files/order.php
     * @magentoConfigFixture default_store multisafepay/general/test_api_key testkey
     * @magentoConfigFixture default_store multisafepay/general/mode 0
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testBuildPaymentOptionsBuilder(): void
    {
        $orderRequest = $this->getObjectManager()->create(OrderRequest::class);
        $order = $this->getOrderWithVisaPaymentMethod();
        $payment = $order->getPayment();
        $this->pluginDetailsBuilder->build($order, $payment, $orderRequest);
        $this->paymentOptionsBuilder->build($order, $payment, $orderRequest);
        $orderRequestData = $orderRequest->getData();

        self::assertArrayHasKey('payment_options', $orderRequestData);
        self::assertNotEmpty($orderRequestData['payment_options']['notification_url']);
        self::assertNotEmpty($orderRequestData['payment_options']['notification_method']);
        self::assertNotEmpty($orderRequestData['payment_options']['redirect_url']);
        self::assertEmpty($orderRequestData['payment_options']['settings']);
    }

    /**
     * @magentoDataFixture   Magento/Sales/_files/order.php
     * @magentoConfigFixture default_store multisafepay/general/test_api_key testkey
     * @magentoConfigFixture default_store multisafepay/general/mode 0
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testBuildEdenredPaymentOptionsWithCoupons(): void
    {
        $orderRequest = $this->getObjectManager()->create(OrderRequest::class);
        $order = $this->getOrderWithVisaPaymentMethod();
        $payment = $order->getPayment();
        $this->pluginDetailsBuilder->build($order, $payment, $orderRequest);

        $paymentOptionsBuilder = $this->getMockBuilder(PaymentOptionsBuilder::class)
            ->setConstructorArgs([
                $this->getObjectManager()->get(PaymentOptions::class),
                $this->getObjectManager()->get(SecureToken::class),
                $this->getObjectManager()->get(StoreManagerInterface::class),
                $this->getEdenredGiftcardConfigProviderMock($order),
            ])
            ->setMethodsExcept(['build'])
            ->getMock();

        $paymentOptionsBuilder->build($order, $payment, $orderRequest);
        $orderRequestData = $orderRequest->getData();
        //
        //self::assertArrayHasKey('payment_options', $orderRequestData);
        //self::assertNotEmpty($orderRequestData['payment_options']['notification_url']);
        //self::assertNotEmpty($orderRequestData['payment_options']['notification_method']);
        //self::assertNotEmpty($orderRequestData['payment_options']['redirect_url']);
        //self::assertEmpty($orderRequestData['payment_options']['settings']);
    }

    /**
     * @param OrderInterface $order
     * @return MockObject
     */
    private function getEdenredGiftcardConfigProviderMock(
        OrderInterface $order
    ): MockObject {
        $edenredGiftcardConfigProvider = $this->getMockBuilder(EdenredGiftcardConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $edenredGiftcardConfigProvider
            ->method('getAvailableCouponsByOrder')
            ->with($order)
            ->willReturn([
                EdenredGiftcardConfigProvider::EDENCOM_COUPON_CODE, EdenredGiftcardConfigProvider::EDENECO_COUPON_CODE
            ]);


        return $edenredGiftcardConfigProvider;
    }
}
