<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk;

readonly class PaymentResponse
{
    /**
     * @var array{
     *        operation: string,
     *        ordernumber: string,
     *        prcode: int,
     *        srcode: int,
     *        resulttext: string,
     *        merordernum?: string,
     * }
     */
    protected array $params;

    protected string $digest;

    protected string $digest1;

    public function __construct(
        string $operation,
        string $ordernumber,
        ?string $merordernum,
        int $prcode,
        int $srcode,
        string $resulttext,
        string $digest,
        string $digest1,
    ) {
        $params = [
            'operation' => $operation,
            'ordernumber' => $ordernumber,
            'prcode' => $prcode,
            'srcode' => $srcode,
            'resulttext' => $resulttext,
        ];
        if ($merordernum !== null) {
            $params['merordernum'] = $merordernum;
        }
        $this->params = $params;
        $this->digest = $digest;
        $this->digest1 = $digest1;
    }

    /**
     * @return array{
     *        operation: string,
     *        ordernumber: string,
     *        prcode: int,
     *        srcode: int,
     *        resulttext: string,
     *        merordernum?: string,
     * }
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getDigest(): string
    {
        return $this->digest;
    }

    public function hasError(): bool
    {
        return $this->params['prcode'] || $this->params['srcode'];
    }

    public function getDigest1(): string
    {
        return $this->digest1;
    }
}
