<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api;

interface GPWebpayApiInterface
{
    public const CREATED = 'CREATED';

    public const PAID = 'PAID';

    public const CANCELED = 'CANCELED';

    /**
     * @param array{
     *    orderNumber: int|string,
     *    amount: int,
     *    currency: string|null,
     *    returnUrl: string,
     *    psd2: array<string, mixed>|null
     * } $order
     * @param array<string>|null $allowedPaymentMethods
     *
     * @return array{
     *      orderId: int,
     *      gatewayLocationUrl: string,
     *  }
     */
    public function create(
        array $order,
        string $merchantNumber,
        bool $sandbox,
        string $clientPrivateKey,
        string $clientPrivateKeyPassword,
        ?string $preferredPaymentMethod,
        ?array $allowedPaymentMethods,
    ): array;

    public function retrieve(
        string $merchantNumber,
        bool $sandbox,
        string $clientPrivateKey,
        string $keyPassword,
    ): string;

    public function verifyResponse(array $responseData, array $config): bool;
}
