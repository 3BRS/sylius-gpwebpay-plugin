<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\Exception\InvalidPayloadException;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\OrderForPayment;

final class OrderForPaymentTest extends TestCase
{
    public function testConstructorWithPsd2Data(): void
    {
        $psd2 = [
            'cardholderInfo' => [
                'cardholderDetails' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ];

        $order = new OrderForPayment(
            currency: 'CZK',
            amount: 10000,
            orderNumber: 12345,
            psd2: $psd2,
        );

        self::assertSame($psd2, $order->getPsd2());
    }

    public function testConstructorWithoutPsd2DefaultsToNull(): void
    {
        $order = new OrderForPayment(
            currency: 'CZK',
            amount: 10000,
            orderNumber: 12345,
        );

        self::assertNull($order->getPsd2());
    }

    public function testToArrayIncludesPsd2(): void
    {
        $psd2 = [
            'cardholderInfo' => [
                'cardholderDetails' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ],
        ];

        $order = new OrderForPayment(
            currency: 'EUR',
            amount: 5000,
            orderNumber: 'ORD-001',
            psd2: $psd2,
        );

        $array = $order->toArray();

        self::assertSame('EUR', $array['currency']);
        self::assertSame(5000, $array['amount']);
        self::assertSame('ORD-001', $array['order_number']);
        self::assertSame($psd2, $array['psd2']);
    }

    public function testToArrayWithNullPsd2(): void
    {
        $order = new OrderForPayment(
            currency: 'CZK',
            amount: 10000,
            orderNumber: 12345,
        );

        $array = $order->toArray();

        self::assertNull($array['psd2']);
    }

    public function testFromArrayWithPsd2(): void
    {
        $psd2 = [
            'cardholderInfo' => [
                'cardholderDetails' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ];

        $order = OrderForPayment::fromArray([
            'currency' => 'CZK',
            'amount' => 10000,
            'order_number' => 12345,
            'psd2' => $psd2,
        ]);

        self::assertSame($psd2, $order->getPsd2());
        self::assertSame('CZK', $order->getCurrency());
        self::assertSame(10000, $order->getAmount());
        self::assertSame(12345, $order->getOrderNumber());
    }

    public function testFromArrayWithoutPsd2KeyDefaultsToNull(): void
    {
        $order = OrderForPayment::fromArray([
            'currency' => 'CZK',
            'amount' => 10000,
            'order_number' => 12345,
        ]);

        self::assertNull($order->getPsd2());
    }

    public function testFromArrayThrowsOnMissingCurrency(): void
    {
        $this->expectException(InvalidPayloadException::class);

        OrderForPayment::fromArray([
            'amount' => 10000,
            'order_number' => 12345,
        ]);
    }

    public function testFromArrayThrowsOnMissingAmount(): void
    {
        $this->expectException(InvalidPayloadException::class);

        OrderForPayment::fromArray([
            'currency' => 'CZK',
            'order_number' => 12345,
        ]);
    }

    public function testFromArrayThrowsOnMissingOrderNumber(): void
    {
        $this->expectException(InvalidPayloadException::class);

        OrderForPayment::fromArray([
            'currency' => 'CZK',
            'amount' => 10000,
        ]);
    }

    public function testRoundTripSerialization(): void
    {
        $psd2 = [
            'cardholderInfo' => [
                'cardholderDetails' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ],
        ];

        $original = new OrderForPayment(
            currency: 'USD',
            amount: 2500,
            orderNumber: 99999,
            psd2: $psd2,
        );

        $restored = OrderForPayment::fromArray($original->toArray());

        self::assertSame($original->getCurrency(), $restored->getCurrency());
        self::assertSame($original->getAmount(), $restored->getAmount());
        self::assertSame($original->getOrderNumber(), $restored->getOrderNumber());
        self::assertSame($original->getPsd2(), $restored->getPsd2());
    }
}
