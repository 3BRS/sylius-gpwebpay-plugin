<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandHandler;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentRepository;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command\CapturePaymentRequest;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\OrderForPayment;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Provider\GpWebPayOrderNumberProviderInterface;

#[AsMessageHandler]
final readonly class CapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private GpWebPayOrderNumberProviderInterface $gpWebPayOrderNumberProvider,
        private PaymentRepository $paymentRepository,
    ) {
    }

    /**
     * Handles @see PaymentRequestInterface::ACTION_CAPTURE
     *
     * Prepare the payment request for capture, later processed on the payment provider website, @see \ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\CaptureHttpResponseProvider::getResponse
     */
    public function __invoke(CapturePaymentRequest $capturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($capturePaymentRequest);

        $orderForPayment = $this->createOrderForPayment($paymentRequest);
        $paymentRequest->setPayload($orderForPayment->toArray());
        $this->paymentRepository->add($paymentRequest);

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
    }

    private function createOrderForPayment(PaymentRequestInterface $paymentRequest): OrderForPayment
    {
        $payment = $paymentRequest->getPayment();
        assert($payment instanceof PaymentInterface);

        $amount = $payment->getAmount();
        assert($amount !== null, 'Payment amount should not be null');

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        return new OrderForPayment(
            currency: $payment->getCurrencyCode(),
            amount: $amount,
            orderNumber: $this->gpWebPayOrderNumberProvider->provideOrderNumber($payment),
            psd2: $this->buildPsd2Data($order),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPsd2Data(OrderInterface $order): ?array
    {
        $customer = $order->getCustomer();
        $billingAddress = $order->getBillingAddress();

        if ($customer === null || $billingAddress === null) {
            return null;
        }

        $email = $customer->getEmail();
        if ($email !== null) {
            $email = self::sanitizeEmail($email);
        }

        return [
            'cardholderInfo' => [
                'cardholderDetails' => [
                    'name' => $billingAddress->getFullName(),
                    'email' => $email,
                ],
            ],
        ];
    }

    private static function sanitizeEmail(string $email): string
    {
        if (str_contains($email, '+')) {
            $parts = explode('+', $email);
            $domainParts = explode('@', $parts[count($parts) - 1]);

            $email = $parts[0] . '@' . $domainParts[count($domainParts) - 1];
        }

        $email = preg_replace('/[^a-zA-Z0-9@.]+/', '', $email);
        assert(is_string($email));

        return $email;
    }
}
