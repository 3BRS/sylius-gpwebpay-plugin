<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk;

interface SignerInterface
{
    public function isPrivateKeyAndPasswordValid(): bool;

    /**
     * @param array<string, scalar|null> $params
     *
     * @throws SignerException
     */
    public function sign(array $params): string;

    /**
     * @param array<string, scalar|null> $params
     *
     * @throws SignerException
     */
    public function verify(array $params, string $digest): bool;
}
