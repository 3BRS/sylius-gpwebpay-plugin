<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->defaults()
        ->public();

    $services->set('theebrs_sylius.gpwebpay.behat.context.ui.admin.managing_payment_methods', \Sylius\Behat\Context\Ui\Admin\ManagingPaymentMethodsContext::class)
        ->decorate('sylius.behat.context.ui.admin.managing_payment_methods')
        ->args([
            service('sylius.behat.page.admin.payment_method.create'),
            service('sylius.behat.page.admin.payment_method.index'),
            service('sylius.behat.page.admin.payment_method.update'),
            service('sylius.behat.current_page_resolver'),
            ['offline' => 'Offline', 'paypal_express_checkout' => 'Paypal Express Checkout', 'stripe_checkout' => 'Stripe Checkout', 'gpwebpay' => 'GP webpay'],
        ]);

    $services->set(\Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Ui\Admin\ManagingPaymentMethodsContext::class, \Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Ui\Admin\ManagingPaymentMethodsContext::class)
        ->args([service(\Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Pages\Admin\PaymentMethod\EditPageInterface::class)]);

    $services->set(\Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Pages\Admin\PaymentMethod\EditPageInterface::class, \Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Pages\Admin\PaymentMethod\EditPage::class)
        ->private()
        ->parent('sylius.behat.page.admin.channel.update');

    $services->set(\Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup\PaymentContext::class, \Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup\PaymentContext::class)
        ->args([
            service('sylius.behat.shared_storage'),
            service('sylius.repository.payment_method'),
            service('sylius.fixture.example_factory.payment_method'),
            service('sylius.manager.payment_method'),
            ['offline' => 'Offline', 'paypal_express_checkout' => 'Paypal Express Checkout', 'stripe_checkout' => 'Stripe Checkout', 'gpwebpay' => 'GP webpay'],
        ]);

    $services->set(\Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup\PaymentRequestContext::class, \Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup\PaymentRequestContext::class)
        ->args([
            service('sylius.behat.shared_storage'),
            service('sylius.repository.order'),
            service('sylius.repository.payment_request'),
            service('sylius.repository.payment_method'),
            service('sylius.repository.shipping_method'),
            service('test.client'),
            service('sylius.factory.order'),
            service('sylius.factory.address'),
            service('sylius.factory.customer'),
            service('sylius.factory.order_item'),
            service('sylius.factory.payment_request'),
            service('sylius_abstraction.state_machine'),
            service('sylius.resolver.product_variant'),
            service('sylius.modifier.order_item_quantity'),
            service('doctrine.orm.entity_manager'),
            service('sylius.random_generator'),
            service('router'),
            service(\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface::class),
        ]);

    $services->alias('tests_threebrs_sylius_gpwebpay_payment_gateway_plugin.context.setup.payment', \Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup\PaymentContext::class);

    $services->alias('tests_threebrs_sylius_gpwebpay_payment_gateway_plugin.context.setup.payment_request', \Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Setup\PaymentRequestContext::class);
};
