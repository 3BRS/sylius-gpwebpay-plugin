<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api;

use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface;

class GPWebpayApiMock implements GPWebpayApiInterface
{
    /**
     * @var callable|null
     */
    private $verifyResponseCallback = null;

    public function create(array $order, string $merchantNumber, bool $sandbox, string $clientPrivateKey, string $clientPrivateKeyPassword, ?string $preferredPaymentMethod, ?array $allowedPaymentMethods): array
    {
        throw new \LogicException('Not implemented in mock class');
    }

    public function retrieve(string $merchantNumber, bool $sandbox, string $clientPrivateKey, string $keyPassword): string
    {
        throw new \LogicException('Not implemented in mock class');
    }

    public function verifyResponse(array $responseData, array $config): bool
    {
        assert(
            $this->verifyResponseCallback !== null,
            'Verify response callback must be set before calling verifyResponse',
        );

        $isValid = call_user_func($this->verifyResponseCallback, $responseData, $config);
        assert(is_bool($isValid));

        return $isValid;
    }

    public function setVerifyResponseCallback(callable $callback): void
    {
        $this->verifyResponseCallback = $callback;
    }

}
