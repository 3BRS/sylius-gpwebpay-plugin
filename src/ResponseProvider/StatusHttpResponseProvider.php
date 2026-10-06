<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final readonly class StatusHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function supports(
        RequestConfiguration|Request $request,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_STATUS;
    }

    public function getResponse(
        RequestConfiguration|Request $request,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        return new RedirectResponse(
            $this->router->generate('sylius_shop_order_thank_you'),
            Response::HTTP_SEE_OTHER,
        );
    }
}
