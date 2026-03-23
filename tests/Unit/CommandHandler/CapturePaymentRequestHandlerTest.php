<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Unit\CommandHandler;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentRepository;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command\CapturePaymentRequest;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandHandler\CapturePaymentRequestHandler;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Provider\GpWebPayOrderNumberProviderInterface;

final class CapturePaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;
    private StateMachineInterface&MockObject $stateMachine;
    private GpWebPayOrderNumberProviderInterface&MockObject $orderNumberProvider;
    private PaymentRepository&MockObject $paymentRepository;
    private CapturePaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderNumberProvider = $this->createMock(GpWebPayOrderNumberProviderInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        $this->handler = new CapturePaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->stateMachine,
            $this->orderNumberProvider,
            $this->paymentRepository,
        );
    }

    public function testPsd2DataIsBuiltFromCustomerAndBillingAddress(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: 'john@example.com',
            billingFullName: 'John Doe',
        );

        self::assertSame([
            'cardholderInfo' => [
                'cardholderDetails' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ], $capturedPayload['psd2']);
    }

    public function testPsd2IsNullWhenCustomerIsNull(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: null,
            billingFullName: null,
            hasCustomer: false,
        );

        self::assertNull($capturedPayload['psd2']);
    }

    public function testPsd2IsNullWhenBillingAddressIsNull(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: 'john@example.com',
            billingFullName: null,
            hasBillingAddress: false,
        );

        self::assertNull($capturedPayload['psd2']);
    }

    public function testEmailPlusAliasIsStripped(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: 'user+tag@example.com',
            billingFullName: 'User',
        );

        $psd2 = $capturedPayload['psd2'];
        self::assertNotNull($psd2);
        self::assertSame(
            'user@example.com',
            $psd2['cardholderInfo']['cardholderDetails']['email'],
        );
    }

    public function testEmailWithMultiplePlusSignsIsHandled(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: 'user+tag1+tag2@example.com',
            billingFullName: 'User',
        );

        $psd2 = $capturedPayload['psd2'];
        self::assertNotNull($psd2);
        self::assertSame(
            'user@example.com',
            $psd2['cardholderInfo']['cardholderDetails']['email'],
        );
    }

    public function testEmailSpecialCharactersAreStripped(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: "user's-email!@example.com",
            billingFullName: 'User',
        );

        $psd2 = $capturedPayload['psd2'];
        self::assertNotNull($psd2);
        $email = $psd2['cardholderInfo']['cardholderDetails']['email'];
        self::assertIsString($email);
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9@.]+$/', $email);
        self::assertSame('usersemail@example.com', $email);
    }

    public function testNullCustomerEmailResultsInNullPsd2Email(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: null,
            billingFullName: 'John Doe',
        );

        $psd2 = $capturedPayload['psd2'];
        self::assertNotNull($psd2);
        self::assertNull(
            $psd2['cardholderInfo']['cardholderDetails']['email'],
        );
    }

    public function testPayloadContainsCurrencyAmountAndOrderNumber(): void
    {
        $capturedPayload = $this->invokeHandlerWithOrderData(
            customerEmail: 'test@example.com',
            billingFullName: 'Test',
            currency: 'EUR',
            amount: 5000,
            orderNumber: 99999,
        );

        self::assertSame('EUR', $capturedPayload['currency']);
        self::assertSame(5000, $capturedPayload['amount']);
        self::assertSame(99999, $capturedPayload['order_number']);
    }

    /**
     * @return array{currency: string|null, amount: int, order_number: int|string, psd2: array{cardholderInfo: array{cardholderDetails: array{name: string|null, email: string|null}}}|null}
     */
    private function invokeHandlerWithOrderData(
        ?string $customerEmail,
        ?string $billingFullName,
        bool $hasCustomer = true,
        bool $hasBillingAddress = true,
        string $currency = 'CZK',
        int $amount = 10000,
        int $orderNumber = 12345,
    ): array {
        $order = $this->createMock(OrderInterface::class);

        if ($hasCustomer) {
            $customer = $this->createMock(CustomerInterface::class);
            $customer->method('getEmail')->willReturn($customerEmail);
            $order->method('getCustomer')->willReturn($customer);
        } else {
            $order->method('getCustomer')->willReturn(null);
        }

        if ($hasBillingAddress) {
            $billingAddress = $this->createMock(AddressInterface::class);
            $billingAddress->method('getFullName')->willReturn($billingFullName);
            $order->method('getBillingAddress')->willReturn($billingAddress);
        } else {
            $order->method('getBillingAddress')->willReturn(null);
        }

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getCurrencyCode')->willReturn($currency);
        $payment->method('getAmount')->willReturn($amount);
        $payment->method('getOrder')->willReturn($order);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $capturedPayload = null;
        $paymentRequest
            ->expects(self::once())
            ->method('setPayload')
            ->with(self::callback(function (mixed $payload) use (&$capturedPayload): bool {
                $capturedPayload = $payload;

                return true;
            }));

        $this->paymentRequestProvider
            ->method('provide')
            ->willReturn($paymentRequest);

        $this->orderNumberProvider
            ->method('provideOrderNumber')
            ->willReturn($orderNumber);

        $this->stateMachine
            ->method('can')
            ->willReturn(false);

        $command = new CapturePaymentRequest('test-hash');
        ($this->handler)($command);

        self::assertIsArray($capturedPayload);
        /** @var array{currency: string|null, amount: int, order_number: int|string, psd2: array{cardholderInfo: array{cardholderDetails: array{name: string|null, email: string|null}}}|null} $capturedPayload */

        return $capturedPayload;
    }
}
