<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandProvider;

use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command\NotifyPaymentRequest;

readonly class NotifyPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_NOTIFY;
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        $request = $this->requestStack->getCurrentRequest();
        $responseData = [];

        if ($request instanceof Request) {
            // GPWebPay sends data via GET parameters
            /** @var array<string, mixed> $responseData */
            $responseData = $request->query->all();
        }

        return new NotifyPaymentRequest($paymentRequest->getId(), $responseData);
    }
}
