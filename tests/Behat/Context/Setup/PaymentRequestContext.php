<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Shipping\Repository\ShippingMethodRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Resource\Generator\RandomnessGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiMock;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface;

final readonly class PaymentRequestContext implements Context
{
    /**
     * @param FactoryInterface<OrderInterface> $orderFactory
     * @param FactoryInterface<AddressInterface> $addressFactory
     * @param FactoryInterface<CustomerInterface> $customerFactory
     * @param FactoryInterface<OrderItemInterface> $orderItemFactory
     * @param GPWebpayApiMock $gpWebpayApi
     */
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private OrderRepositoryInterface $orderRepository,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ShippingMethodRepositoryInterface $shippingMethodRepository,
        private KernelBrowser $client,
        private FactoryInterface $orderFactory,
        private FactoryInterface $addressFactory,
        private FactoryInterface $customerFactory,
        private FactoryInterface $orderItemFactory,
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private StateMachineInterface $stateMachine,
        private ProductVariantResolverInterface $variantResolver,
        private OrderItemQuantityModifierInterface $itemQuantityModifier,
        private ObjectManager $objectManager,
        private RandomnessGeneratorInterface $randomnessGenerator,
        private RouterInterface $router,
        private GPWebpayApiInterface $gpWebpayApi,
    ) {
        assert($gpWebpayApi instanceof GPWebpayApiMock, 'GPWebpayApiInterface must be an instance of GPWebpayApiMock for testing purposes');
    }

    #[Given('I have placed an order with :paymentMethodName payment method')]
    public function iHavePlacedAnOrderWithPaymentMethod(string $paymentMethodCode): void
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $paymentMethodCode]);
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            throw new \RuntimeException(sprintf('Payment method with code "%s" not found', $paymentMethodCode));
        }

        $customer = $this->getCustomer();
        $product = $this->getProduct();
        $address = $this->getAddress();
        $shippingMethod = $this->getShippingMethod();

        $this->placeOrder($product, $shippingMethod, $address, $paymentMethod, $customer);
        $this->objectManager->flush();
    }

    #[Given('the order has a notify payment request in :state state')]
    public function thePaymentRequestIsInState(string $state): void
    {
        $order = $this->sharedStorage->get('order');
        assert($order instanceof OrderInterface);

        $payment = $order->getLastPayment();
        assert($payment instanceof PaymentInterface);

        $paymentRequest = $this->paymentRequestFactory->create($payment, $payment->getMethod());
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_NOTIFY);

        $this->paymentRequestRepository->add($paymentRequest);
        $this->objectManager->flush();

        $paymentRequest->setState($state);
        $this->objectManager->flush();

        $this->sharedStorage->set('payment_request', $paymentRequest);
    }

    #[When('GPWebPay sends a successful payment notification')]
    public function gpwebpaySendsASuccessfulPaymentNotification(): void
    {
        $paymentRequest = $this->sharedStorage->get('payment_request');
        assert($paymentRequest instanceof PaymentRequestInterface);

        // Simulate GPWebPay notification parameters
        $notificationData = [
            'OPERATION' => 'CREATE_ORDER',
            'ORDERNUMBER' => '12345',
            'PRCODE' => '0', // Success code
            'SRCODE' => '0',
            'RESULTTEXT' => 'OK',
            'DIGEST' => 'test_digest',
            'DIGEST1' => 'test_digest1',
        ];

        $this->gpWebpayApi->setVerifyResponseCallback(
            function (array $responseData, array $config): bool {
                // Simulate verification logic
                return $responseData['DIGEST'] === 'test_digest' &&
                    $responseData['DIGEST1'] === 'test_digest1';
            },
        );

        $notifyCallbackUrl = $this->router->generate(
            'sylius_payment_request_notify',
            ['hash' => $paymentRequest->getHash()],
            RouterInterface::ABSOLUTE_URL,
        );
        $this->client->request('GET', $notifyCallbackUrl, $notificationData);
        $response = $this->client->getResponse();
        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('GPWebPay notification failed with status code %d', $response->getStatusCode()));
        }

        $this->sharedStorage->set('response', $response);
    }

    #[When('GPWebPay sends a failed payment notification')]
    public function gpwebpaySendsAFailedPaymentNotification(): void
    {
        $paymentRequest = $this->sharedStorage->get('payment_request');
        assert($paymentRequest instanceof PaymentRequestInterface);

        // Simulate GPWebPay notification parameters for failed payment
        $notificationData = [
            'OPERATION' => 'CREATE_ORDER',
            'ORDERNUMBER' => '12345',
            'PRCODE' => '1', // Error code
            'SRCODE' => '1',
            'RESULTTEXT' => 'FAILED',
            'DIGEST' => 'test_digest',
            'DIGEST1' => 'test_digest1',
        ];

        $this->gpWebpayApi->setVerifyResponseCallback(
            function (array $responseData, array $config): bool {
                // Simulate verification logic
                return $responseData['DIGEST'] === 'test_digest' &&
                    $responseData['DIGEST1'] === 'test_digest1';
            },
        );

        $notifyCallbackUrl = $this->router->generate(
            'sylius_payment_request_notify',
            ['hash' => $paymentRequest->getHash()],
            RouterInterface::RELATIVE_PATH,
        );
        $this->client->request('GET', $notifyCallbackUrl, $notificationData);

        $this->sharedStorage->set('response', $this->client->getResponse());
    }

    #[Then('the payment request should be completed')]
    public function thePaymentRequestShouldBeCompleted(): void
    {
        $paymentRequest = $this->sharedStorage->get('payment_request');
        assert($paymentRequest instanceof PaymentRequestInterface);

        // Refresh from database
        $paymentRequest = $this->paymentRequestRepository->findOneBy(['hash' => $paymentRequest->getHash()]);
        assert($paymentRequest !== null);

        if ($paymentRequest->getState() !== PaymentRequestInterface::STATE_COMPLETED) {
            throw new \RuntimeException(sprintf('Expected payment request to be completed, but it is in state "%s"', $paymentRequest->getState()));
        }
    }

    #[Then('the payment request should be failed')]
    public function thePaymentRequestShouldBeFailed(): void
    {
        $paymentRequest = $this->sharedStorage->get('payment_request');
        assert($paymentRequest instanceof PaymentRequestInterface);

        // Refresh from database
        $paymentRequest = $this->paymentRequestRepository->findOneBy(['hash' => $paymentRequest->getHash()]);
        assert($paymentRequest instanceof PaymentRequestInterface);

        if ($paymentRequest->getState() !== PaymentRequestInterface::STATE_FAILED) {
            throw new \RuntimeException(
                sprintf(
                    'Expected payment request to be failed, but it is in state "%s"',
                    $paymentRequest->getState(),
                ),
            );
        }
    }

    #[Then('the order should be marked as paid')]
    public function theOrderShouldBeMarkedAsPaid(): void
    {
        $order = $this->sharedStorage->get('order');
        assert($order instanceof OrderInterface);

        // Refresh from database
        $order = $this->orderRepository->findOneBy(['id' => $order->getId()]);

        if ($order->getPaymentState() !== OrderPaymentStates::STATE_PAID) {
            throw new \RuntimeException(sprintf('Expected order to be paid, but payment state is "%s"', $order->getPaymentState()));
        }
    }

    #[Then('the order should remain unpaid')]
    public function theOrderShouldRemainUnpaid(): void
    {
        $order = $this->sharedStorage->get('order');
        assert($order instanceof OrderInterface);

        // Refresh from database
        $order = $this->orderRepository->findOneBy(['id' => $order->getId()]);

        if ($order->getPaymentState() === OrderPaymentStates::STATE_PAID) {
            throw new \RuntimeException('Expected order to remain unpaid, but it is marked as paid');
        }
    }

    private function getCustomer(): CustomerInterface
    {
        if ($this->sharedStorage->has('customer')) {
            return $this->sharedStorage->get('customer');
        }

        /** @var CustomerInterface $customer */
        $customer = $this->customerFactory->createNew();
        $customer->setEmail('customer@example.com');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');

        $this->sharedStorage->set('customer', $customer);

        return $customer;
    }

    private function getProduct(): ProductInterface
    {
        if ($this->sharedStorage->has('product')) {
            return $this->sharedStorage->get('product');
        }

        throw new \RuntimeException('No product found in shared storage. Please ensure a product is created in previous steps.');
    }

    private function getAddress(): AddressInterface
    {
        if ($this->sharedStorage->has('address')) {
            return $this->sharedStorage->get('address');
        }

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setFirstName('John');
        $address->setLastName('Doe');
        $address->setStreet('Main St. 123');
        $address->setCountryCode('US');
        $address->setCity('New York');
        $address->setPostcode('10001');

        $this->sharedStorage->set('address', $address);

        return $address;
    }

    private function getShippingMethod(): ShippingMethodInterface
    {
        if ($this->sharedStorage->has('shipping_method')) {
            return $this->sharedStorage->get('shipping_method');
        }

        $shippingMethod = $this->shippingMethodRepository->findOneBy([]);
        if (!$shippingMethod instanceof ShippingMethodInterface) {
            throw new \RuntimeException('No shipping method found. Please ensure a shipping method is created in previous steps.');
        }

        $this->sharedStorage->set('shipping_method', $shippingMethod);

        return $shippingMethod;
    }

    private function placeOrder(
        ProductInterface $product,
        ShippingMethodInterface $shippingMethod,
        AddressInterface $address,
        PaymentMethodInterface $paymentMethod,
        CustomerInterface $customer,
    ): void {
        $variant = $this->getProductVariant($product);

        $channelPricing = $variant->getChannelPricingForChannel($this->sharedStorage->get('channel'));

        /** @var OrderItemInterface $item */
        $item = $this->orderItemFactory->createNew();
        $item->setVariant($variant);
        $item->setUnitPrice($channelPricing->getPrice());

        $this->itemQuantityModifier->modify($item, 1);

        $order = $this->createOrder($customer, '00000001');
        $order->addItem($item);

        $this->checkoutUsing($order, $shippingMethod, clone $address, $paymentMethod, true);

        $this->objectManager->persist($order);
        $this->sharedStorage->set('order', $order);
    }

    private function getProductVariant(ProductInterface $product): ProductVariantInterface
    {
        /** @var ProductVariantInterface|null $variant */
        $variant = $this->variantResolver->getVariant($product);

        if ($variant === null) {
            throw new \RuntimeException(sprintf('Product "%s" has no variant', $product->getCode()));
        }

        return $variant;
    }

    private function createOrder(
        CustomerInterface $customer,
        ?string $number = null,
        ?ChannelInterface $channel = null,
    ): OrderInterface {
        $order = $this->createCart($customer, $channel);
        $order->setTokenValue($this->generateToken());

        if (null !== $number) {
            $order->setNumber($number);
        }

        $order->completeCheckout();

        return $order;
    }

    private function createCart(CustomerInterface $customer, ?ChannelInterface $channel = null): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->orderFactory->createNew();

        $order->setCustomer($customer);
        $order->setChannel($channel ?: $this->sharedStorage->get('channel'));
        $order->setLocaleCode($order->getChannel()->getDefaultLocale()->getCode());
        $order->setCurrencyCode($order->getChannel()->getBaseCurrency()->getCode());

        return $order;
    }

    private function checkoutUsing(
        OrderInterface $order,
        ShippingMethodInterface $shippingMethod,
        AddressInterface $address,
        PaymentMethodInterface $paymentMethod,
        bool $completeOrder = true,
    ): void {
        $order->setShippingAddress($address);
        $order->setBillingAddress(clone $address);

        $this->applyTransitionOnOrderCheckout($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);

        $this->proceedSelectingShippingAndPaymentMethod($order, $shippingMethod, $paymentMethod);
        if ($completeOrder) {
            $this->completeCheckout($order);
        }
    }

    private function applyTransitionOnOrderCheckout(OrderInterface $order, string $transition): void
    {
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, $transition);
    }

    private function proceedSelectingShippingAndPaymentMethod(
        OrderInterface $order,
        ShippingMethodInterface $shippingMethod,
        PaymentMethodInterface $paymentMethod,
    ): void {
        foreach ($order->getShipments() as $shipment) {
            $shipment->setMethod($shippingMethod);
        }

        $this->applyTransitionOnOrderCheckout($order, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);

        $payment = $order->getLastPayment();
        $payment->setMethod($paymentMethod);

        $this->applyTransitionOnOrderCheckout($order, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
    }

    private function completeCheckout(OrderInterface $order): void
    {
        $this->applyTransitionOnOrderCheckout($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
    }

    private function generateToken(): string
    {
        return $this->randomnessGenerator->generateUriSafeString(10);
    }
}
