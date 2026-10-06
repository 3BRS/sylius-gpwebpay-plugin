<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Api;

use Alcohol\ISO4217;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\Api;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\GpWebPayPaymentRequest;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\PaymentResponse;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\PaymentResponseException;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\Signer;

class GPWebpayApi implements GPWebpayApiInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private RequestStack $requestStack,
    ) {
    }

    protected function createApi(
        bool $sandbox,
        string $clientPrivateKey,
        string $keyPassword,
        string $merchantNumber,
    ): Api {
        $serverCert = $sandbox
            ? __DIR__ . '/../Resources/keys/serverKeys/sandbox/gpe.signing_test.pem'
            : __DIR__ . '/../Resources/keys/serverKeys/prod/gpe.signing_prod.pem';

        $apiEndpoint = $sandbox
            ? 'https://test.3dsecure.gpwebpay.com/pgw/order.do'
            : 'https://3dsecure.gpwebpay.com/pgw/order.do';

        $signer = new Signer($clientPrivateKey, $keyPassword, $serverCert);

        return new Api($merchantNumber, $apiEndpoint, $signer);
    }

    protected function getCurrency(?string $currencyCode): int
    {
        if ($currencyCode === null) {
            throw new \RuntimeException('Required currency code is not set.');
        }
        $currency = (new ISO4217())->getByAlpha3($currencyCode);

        return (int) $currency['numeric'];
    }

    /**
     * @param array{
     *     orderNumber: int|string,
     *     amount: int,
     *     currency: string|null,
     *     returnUrl: string,
     *     psd2: array<string, string|array<string, mixed>>|null,
     * } $order
     * @param array<string>|null $allowedPaymentMethods
     *
     * @return array{
     *     orderId: int,
     *     gatewayLocationUrl: string,
     * }
     */
    public function create(
        array $order,
        string $merchantNumber,
        bool $sandbox,
        string $clientPrivateKey,
        string $clientPrivateKeyPassword,
        ?string $preferredPaymentMethod,
        ?array $allowedPaymentMethods,
    ): array {
        $api = $this->createAPI($sandbox, $clientPrivateKey, $clientPrivateKeyPassword, $merchantNumber);

        $orderNumber = (int) $order['orderNumber'];
        $amount = $order['amount'] / 100;
        $currency = $this->getCurrency($order['currency']);
        $depositFlag = 1;
        $url = $order['returnUrl'];
        $merOrderNumber = null;
        $psd2 = $order['psd2'] ?? null;

        $request = new GpWebPayPaymentRequest($orderNumber, $amount, $currency, $depositFlag, $url, $merOrderNumber);
        if ($preferredPaymentMethod !== null && $preferredPaymentMethod !== '') {
            $request->setPreferredPaymentMethod($preferredPaymentMethod);
        }
        if ($allowedPaymentMethods !== null && count($allowedPaymentMethods) > 0) {
            $request->setAllowedPaymentMethods(implode(',', $allowedPaymentMethods));
        }

        if ($psd2 !== null) {
            $request->setPsd2Data($psd2);
        }

        return [
            'orderId' => $orderNumber,
            'gatewayLocationUrl' => $api->createPaymentRequestUrl($request),
        ];
    }

    public function retrieve(
        string $merchantNumber,
        bool $sandbox,
        string $clientPrivateKey,
        string $keyPassword,
    ): string {
        $request = $this->requestStack->getMainRequest();
        assert($request !== null);

        $operation = (string) $request->query->get('OPERATION');
        $ordernumber = (string) $request->query->get('ORDERNUMBER');
        $merordernum = $request->query->get('MERORDERNUM');
        $merordernum = $merordernum !== null
            ? (string) $merordernum
            : null;
        $prcode = (int) $request->query->get('PRCODE');
        $srcode = (int) $request->query->get('SRCODE');
        $resulttext = (string) $request->query->get('RESULTTEXT');
        $digest = (string) $request->query->get('DIGEST');
        $digest1 = (string) $request->query->get('DIGEST1');

        $response = new PaymentResponse($operation, $ordernumber, $merordernum, $prcode, $srcode, $resulttext, $digest, $digest1);

        try {
            $api = $this->createAPI($sandbox, $clientPrivateKey, $keyPassword, $merchantNumber);
            $api->verifyPaymentResponse($response);
        } catch (PaymentResponseException | \Exception $e) {
            $this->logger->error($e->getMessage());

            return GPWebpayApiInterface::CANCELED;
        }

        if ($response->hasError()) {
            return GPWebpayApiInterface::CANCELED;
        }

        return GPWebpayApiInterface::PAID;
    }

    public function verifyResponse(array $responseData, array $config): bool
    {
        if (empty($responseData) || !isset($config['merchantNumber'], $config['keyPrivate'], $config['keyPrivatePassword'])) {
            return false;
        }

        try {
            $operation = (string) ($responseData['OPERATION'] ?? '');
            $ordernumber = (string) ($responseData['ORDERNUMBER'] ?? '');
            $merordernum = isset($responseData['MERORDERNUM']) ? (string) $responseData['MERORDERNUM'] : null;
            $prcode = (int) ($responseData['PRCODE'] ?? -1);
            $srcode = (int) ($responseData['SRCODE'] ?? -1);
            $resulttext = (string) ($responseData['RESULTTEXT'] ?? '');
            $digest = (string) ($responseData['DIGEST'] ?? '');
            $digest1 = (string) ($responseData['DIGEST1'] ?? '');

            $response = new PaymentResponse($operation, $ordernumber, $merordernum, $prcode, $srcode, $resulttext, $digest, $digest1);

            $api = $this->createAPI(
                (bool) ($config['sandbox'] ?? true),
                (string) $config['keyPrivate'],
                (string) $config['keyPrivatePassword'],
                (string) $config['merchantNumber'],
            );

            $api->verifyPaymentResponse($response);

            return true;
        } catch (PaymentResponseException | \Exception $e) {
            $this->logger->error('GPWebPay response verification failed: ' . $e->getMessage());

            return false;
        }
    }
}
