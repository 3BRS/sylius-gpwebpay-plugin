<h1 align="center">
    GP webpay Payment Gateway Plugin
</h1>

## Features

* Card payments as supported by [GP webpay](https://www.gpwebpay.cz/en/home/)
* Fully integrated as [Sylius](https://sylius.com/) payment method
* Using more different gateways at once or per channel

<p align="center">
	<img src="https://raw.githubusercontent.com/3BRS/sylius-gpwebpay-plugin/main/doc/admin-11.png"/>
</p>
<p align="center">
	<img src="https://raw.githubusercontent.com/3BRS/sylius-gpwebpay-plugin/main/doc/admin-2.png"/>
</p>

## Requirements

| Package | Version         |
|---------|-----------------|
| PHP     | ^8.2            |
| Sylius  | ^2.1            |
| Symfony | ^7.4 \|\| ^8.0  |

## Installation

1. Run `$ composer require
   3brs/sylius-gpwebpay-plugin`.
2. Add plugin classes to your `config/bundles.php`:

   ```php
   return [
      ...
      ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ThreeBRSSyliusGPWebpayPaymentGatewayPlugin::class => ['all' => true],
   ];
   ```
3. Load plugin configuration by `config/packages/threebrs_sylius_gpwebpay_payment_gateway_plugin.yaml`:

   ```yaml
   imports:
    - { resource: "@ThreeBRSSyliusGPWebpayPaymentGatewayPlugin/Resources/config/config.yaml" }
   ```

4. Generate keys to keep gateway credentials safe:

   ```bash
   bin/console sylius:payment:generate-key
   ```
  
## Usage

* <b>Create GP webpay payment type</b><br>in Sylius admin panel, _Configuration -> Payment methods_<br>

## Sylius 2 pay workflow

- Customer hit _Pay_ button
- Request goes to `\Sylius\Bundle\CoreBundle\OrderPay\Controller\OrderPayController::payAction`
- That will emit redirect 302 by `\Sylius\Bundle\CoreBundle\OrderPay\Provider\PaymentRequestPayResponseProvider::getResponse` to route like `/en_US/payment-request/pay/0197204a-8284-7301-9b30-e151c7d14ec5`
- That request goes to `\Sylius\Bundle\CoreBundle\OrderPay\Action\PaymentRequestPayAction::__invoke`
  - that will dispatch command to process the payment in `\Sylius\Bundle\PaymentBundle\Processor\HttpResponseProcessor::process`
  - that will find command provider fitting the payment method in `\Sylius\Bundle\PaymentBundle\CommandProvider\AbstractServiceCommandProvider::provide`, in our case `\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandProvider\CapturePaymentRequestCommandProvider`
  - the provider will give command `\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Command\CapturePaymentRequest`
  - that will be handled by `\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\CommandHandler\CapturePaymentRequestHandler`
    - ⚠️ if messenger is configured as async, the payment will not be processed immediately and customer will end on Pay page again ⚠️
    - that command handler may resolve the payment, but in our case it will just prepare payload for the payment gateway webpage
  - then the "capture" response is processed by `\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\CaptureHttpResponseProvider` which in our case will return a redirect to the payment gateway webpage

## Development

### Usage

- Develop your plugin in `/src`
- See `bin/` for useful commands

### Testing

After your changes you must ensure that the tests are still passing.

```bash
make ci
```

License
-------
This library is under the MIT license.

Credits
-------
Developed by [3BRS](https://3brs.com)<br>
Forked from [manGoweb](https://github.com/mangoweb-sylius/SyliusPaymentRestrictionsPlugin).
