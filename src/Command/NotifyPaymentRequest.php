<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command;

use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareInterface;
use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareTrait;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandHandler\NotifyPaymentRequestHandler;

/**
 * Processed by @see NotifyPaymentRequestHandler
 */
class NotifyPaymentRequest implements PaymentRequestHashAwareInterface
{
    use PaymentRequestHashAwareTrait;

    /**
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        ?string $hash,
        private readonly array $responseData = [],
    ) {
        $this->hash = $hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
