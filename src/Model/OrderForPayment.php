<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model;

use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\Exception\InvalidPayloadException;

readonly class OrderForPayment
{
    /**
     * @param array{
     *     currency?: string|null,
     *     amount?: int,
     *     order_number?: int|string,
     *     psd2?: array<string, mixed>|null,
     * } $data
     *
     * @throws InvalidPayloadException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency']
                      ?? throw new InvalidPayloadException('Currency is required in payment payload'),
            amount: $data['amount']
                    ?? throw new InvalidPayloadException('Amount is required in payment payload'),
            orderNumber: $data['order_number']
                         ?? throw new InvalidPayloadException('Order number is required in payment payload'),
            psd2: $data['psd2'] ?? null,
        );
    }

    /**
     * @param array<string, mixed>|null $psd2
     */
    public function __construct(
        private ?string $currency,
        private int $amount,
        private int | string $orderNumber,
        private ?array $psd2 = null,
    ) {
    }

    /**
     * @return array{
     *     currency: string|null,
     *     amount: int,
     *     order_number: int|string,
     *     psd2: array<string, mixed>|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->getCurrency(),
            'amount' => $this->getAmount(),
            'order_number' => $this->getOrderNumber(),
            'psd2' => $this->getPsd2(),
        ];
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getOrderNumber(): int | string
    {
        return $this->orderNumber;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPsd2(): ?array
    {
        return $this->psd2;
    }
}
