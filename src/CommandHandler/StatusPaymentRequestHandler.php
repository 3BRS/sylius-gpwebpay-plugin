<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandHandler;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command\StatusPaymentRequest;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\Exception\InvalidPaymentGatewayConfiguration;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\Partials\GpWebPayApiConfigurationTrait;

#[AsMessageHandler]
final readonly class StatusPaymentRequestHandler
{
    use GpWebPayApiConfigurationTrait;

    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private GPWebpayApiInterface $gpWebpayApi,
        private StateMachineInterface $stateMachine,
    ) {
    }

    /**
     * @see PaymentRequestInterface::ACTION_STATUS
     */
    public function __invoke(StatusPaymentRequest $statusPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($statusPaymentRequest);

        $payment = $paymentRequest->getPayment();
        assert($payment instanceof PaymentInterface);

        $gpWebPayConfig = $paymentRequest->getPayment()->getMethod()?->getGatewayConfig()?->getConfig();
        if ($gpWebPayConfig === null) {
            throw new InvalidPaymentGatewayConfiguration(
                'GpWebPay payment method configuration is missing',
            );
        }

        $status = $this->gpWebpayApi->retrieve(
            merchantNumber  : $this->getMerchantNumber($gpWebPayConfig),
            sandbox         : $this->isSandbox($gpWebPayConfig),
            clientPrivateKey: $this->getClientPrivateKey($gpWebPayConfig),
            keyPassword     : $this->getClientPrivateKeyPassword($gpWebPayConfig),
        );

        if ($status === GPWebpayApiInterface::CREATED) {
            if ($this->stateMachine->can(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_PROCESS,
            )) {
                $this->stateMachine->apply(
                    $paymentRequest,
                    PaymentRequestTransitions::GRAPH,
                    PaymentRequestTransitions::TRANSITION_PROCESS,
                );
            }

            return;
        }

        if ($status === GPWebpayApiInterface::CANCELED) {
            if ($this->stateMachine->can(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_CANCEL,
            )) {
                $this->stateMachine->apply(
                    $paymentRequest,
                    PaymentRequestTransitions::GRAPH,
                    PaymentRequestTransitions::TRANSITION_CANCEL,
                );
            }
            // do not cancel payment, because same payment can have multiple requests

            return;
        }

        if ($this->stateMachine->can(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        )) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_COMPLETE,
            );
        }

        if ($this->stateMachine->can(
            $payment,
            PaymentTransitions::GRAPH,
            PaymentTransitions::TRANSITION_COMPLETE,
        )) {
            $this->stateMachine->apply(
                $payment,
                PaymentTransitions::GRAPH,
                PaymentTransitions::TRANSITION_COMPLETE,
            );
        }
    }
}
