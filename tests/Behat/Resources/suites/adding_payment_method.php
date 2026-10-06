<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Ui\Admin\ManagingPaymentMethodsContext;

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('adding_payment_method'))
                    ->withContexts(
                        'sylius.behat.context.hook.doctrine_orm',
                        'sylius.behat.context.hook.session',
                        'sylius.behat.context.setup.channel',
                        'sylius.behat.context.setup.currency',
                        'sylius.behat.context.setup.locale',
                        'sylius.behat.context.setup.order',
                        'sylius.behat.context.setup.payment',
                        'sylius.behat.context.setup.product',
                        'sylius.behat.context.setup.admin_security',
                        'sylius.behat.context.setup.shipping',
                        'sylius.behat.context.setup.user',
                        'sylius.behat.context.setup.zone',
                        'sylius.behat.context.transform.address',
                        'sylius.behat.context.transform.customer',
                        'sylius.behat.context.transform.locale',
                        'sylius.behat.context.transform.payment',
                        'sylius.behat.context.transform.product',
                        'sylius.behat.context.transform.shared_storage',
                        'sylius.behat.context.transform.shipping_method',
                        'theebrs_sylius.gpwebpay.behat.context.ui.admin.managing_payment_methods',
                        ManagingPaymentMethodsContext::class,
                        'sylius.behat.context.ui.admin.notification',
                        'sylius.behat.context.ui.shop.locale',
                    )
                    ->withFilter(new TagFilter('@adding_payment_method && @ui')),
            ),
    );
