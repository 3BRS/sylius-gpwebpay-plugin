<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandHandler;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command\NotifyPaymentRequest;

#[AsMessageHandler]
final readonly class NotifyPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private GPWebpayApiInterface $gpWebPayApi,
    ) {
    }

    /**
     * Handles @see PaymentRequestInterface::ACTION_NOTIFY
     * and @see \Sylius\Bundle\PaymentBundle\Action\PaymentRequestNotifyAction::__invoke
     *
     * Process GPWebPay callback/webhook notifications
     */
    public function __invoke(NotifyPaymentRequest $notifyPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($notifyPaymentRequest);
        $responseData = $notifyPaymentRequest->getResponseData();

        // Store the response data for later processing
        $paymentRequest->setResponseData($responseData);

        // Verify the GPWebPay response signature and process the result
        $isValid = $this->gpWebPayApi->verifyResponse($responseData, $paymentRequest->getMethod()->getGatewayConfig()?->getConfig() ?? []);

        if (!$isValid) {
            // Mark as failed if signature verification fails
            if ($this->stateMachine->can(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            )) {
                $this->stateMachine->apply(
                    $paymentRequest,
                    PaymentRequestTransitions::GRAPH,
                    PaymentRequestTransitions::TRANSITION_FAIL,
                );
            }

            return;
        }

        if ($this->isPaymentRequestSuccessful($responseData)) {
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

            if ($responseData['OPERATION'] === 'CREATE_ORDER') {
                $payment = $paymentRequest->getPayment();
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

            return;
        }

        // Mark as failed for non-successful PRCODE
        if ($this->stateMachine->can(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_FAIL,
        )) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );
        }
    }

    /**
     * Validates that return data matches info about successfully paid payment according to GP webpay documentation.
     *
     * Based on GP webpay WS API documentation:
     * - PRCODE/primaryReturnCode must be 0 (OK)
     * - SRCODE/secondaryReturnCode should be 0 (no additional error info)
     *
     * @param array<string, mixed> $responseData
     */
    private function isPaymentRequestSuccessful(array $responseData): bool
    {
        // Primary return code must be 0 (OK) for successful payment
        if (!isset($responseData['PRCODE']) || $responseData['PRCODE'] !== '0') {
            return false;
        }

        // Secondary return code should be 0 for successful payment (no additional error info)
        if (isset($responseData['SRCODE']) && $responseData['SRCODE'] !== '0') {
            return false;
        }

        return true;
    }
}
