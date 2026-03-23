<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Form\Type\GPWebpayGatewayConfigurationType;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\Exception\InvalidPayloadException;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\OrderForPayment;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\Exception\InvalidPaymentGatewayConfiguration;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\Partials\GpWebPayApiConfigurationTrait;

final readonly class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    use GpWebPayApiConfigurationTrait;

    public function __construct(
        private GPWebpayApiInterface $gpWebPayApi,
        private RouterInterface $router,
    ) {
    }

    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE;
    }

    public function getResponse(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        $payloadArray = $paymentRequest->getPayload();
        if (!is_array($payloadArray)) {
            throw new InvalidPayloadException('Payment request payload expected to be an array');
        }
        // @phpstan-ignore-next-line
        $orderForPayment = OrderForPayment::fromArray($payloadArray);

        $gpWebPayConfig = $paymentRequest->getPayment()->getMethod()?->getGatewayConfig()?->getConfig();
        if ($gpWebPayConfig === null) {
            throw new InvalidPaymentGatewayConfiguration(
                'GpWebPay payment method configuration is missing',
            );
        }

        $requestData = $this->gpWebPayApi->create(
            [
                'orderNumber' => (int) $orderForPayment->getOrderNumber(),
                'amount' => $orderForPayment->getAmount(),
                'currency' => $orderForPayment->getCurrency(),
                'returnUrl' => $this->router->generate(
                    'sylius_shop_order_after_pay',
                    ['hash' => $paymentRequest->getHash()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'psd2' => $orderForPayment->getPsd2(),
            ],
            $this->getMerchantNumber($gpWebPayConfig),
            $this->isSandbox($gpWebPayConfig),
            $this->getClientPrivateKey($gpWebPayConfig),
            $this->getClientPrivateKeyPassword($gpWebPayConfig),
            $this->getPreferredPaymentMethod($gpWebPayConfig),
            $this->getAllowedPaymentMethods($gpWebPayConfig),
        );

        return new RedirectResponse(
            $requestData['gatewayLocationUrl'],
            Response::HTTP_SEE_OTHER,
        );
    }

    /**
     * @param array<string, mixed> $gpWebPayConfig
     *
     * @return array<string>|null
     */
    private function getAllowedPaymentMethods(array $gpWebPayConfig): ?array
    {
        $allowedPaymentMethods = $this->getValueFromGatewayConfiguration(
            GPWebpayGatewayConfigurationType::ALLOWED_PAYMENT_METHODS,
            $gpWebPayConfig,
        );

        return $allowedPaymentMethods
            ? (array) $allowedPaymentMethods
            : null;
    }

    private function getPreferredPaymentMethod(array $gpWebPayConfig): ?string
    {
        $preferredPaymentMethod = $this->getValueFromGatewayConfiguration(
            GPWebpayGatewayConfigurationType::PREFERRED_PAYMENT_METHOD,
            $gpWebPayConfig,
        );
        assert($preferredPaymentMethod === null || is_scalar($preferredPaymentMethod));
        $preferredPaymentMethod = (string) $preferredPaymentMethod;
        if ($preferredPaymentMethod === '') {
            return null;
        }

        return $preferredPaymentMethod;
    }
}
