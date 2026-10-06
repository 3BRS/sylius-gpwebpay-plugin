<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\Partials;

use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Form\Type\GPWebpayGatewayConfigurationType;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\ResponseProvider\Exception\InvalidPaymentGatewayConfiguration;

trait GpWebPayApiConfigurationTrait
{
    private function getMerchantNumber(array $gpWebPayConfig): string
    {
        $merchantNumber = $this->getValueFromGatewayConfiguration(
            GPWebpayGatewayConfigurationType::MERCHANT_NUMBER,
            $gpWebPayConfig,
        );
        assert($merchantNumber === null || is_scalar($merchantNumber));
        $merchantNumber = (string) $merchantNumber;
        if ($merchantNumber === '') {
            throw new InvalidPaymentGatewayConfiguration(
                'GpWebPay merchant number is missing in the configuration',
            );
        }

        return $merchantNumber;
    }

    private function isSandbox(array $gpWebPayConfig): bool
    {
        $isSandbox = $this->getValueFromGatewayConfiguration(
            GPWebpayGatewayConfigurationType::SANDBOX,
            $gpWebPayConfig,
        );
        assert($isSandbox === null || is_scalar($isSandbox));

        return (bool) $isSandbox;
    }

    private function getClientPrivateKey(array $gpWebPayConfig): string
    {
        $clientPrivateKey = $this->getValueFromGatewayConfiguration(
            GPWebpayGatewayConfigurationType::KEY_PRIVATE,
            $gpWebPayConfig,
        );
        assert($clientPrivateKey === null || is_scalar($clientPrivateKey));
        $clientPrivateKey = (string) $clientPrivateKey;
        if ($clientPrivateKey === '') {
            throw new InvalidPaymentGatewayConfiguration(
                'GpWebPay client private key is missing in the configuration',
            );
        }

        return $clientPrivateKey;
    }

    private function getClientPrivateKeyPassword(array $gpWebPayConfig): string
    {
        $clientPrivateKeyPassword = $this->getValueFromGatewayConfiguration(
            GPWebpayGatewayConfigurationType::KEY_PRIVATE_PASSWORD,
            $gpWebPayConfig,
        );
        assert($clientPrivateKeyPassword === null || is_scalar($clientPrivateKeyPassword));
        $clientPrivateKeyPassword = (string) $clientPrivateKeyPassword;
        if ($clientPrivateKeyPassword === '') {
            throw new InvalidPaymentGatewayConfiguration(
                'GpWebPay client private key password is missing in the configuration',
            );
        }

        return $clientPrivateKeyPassword;
    }

    private function getValueFromGatewayConfiguration(
        string $key,
        array $gpWebPayConfig,
    ): string|int|float|array|bool|null {
        if (!array_key_exists($key, $gpWebPayConfig)) {
            throw new InvalidPaymentGatewayConfiguration(
                sprintf('GpWebPay configuration key "%s" is missing', $key),
            );
        }

        $value = $gpWebPayConfig[$key];
        assert($value === null || is_scalar($value) || is_array($value));

        return $value;
    }
}
