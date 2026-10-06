<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk;

class Signer implements SignerInterface
{
    private ?\OpenSSLAsymmetricKey $privateKeyResource = null;

    private string $publicKey;

    private ?\OpenSSLAsymmetricKey $publicKeyResource = null;

    public function __construct(
        private readonly string $privateKey,
        private readonly string $privateKeyPassword,
        string $publicKey,
    ) {
        $this->publicKey = $publicKey;
    }

    public function isPrivateKeyAndPasswordValid(): bool
    {
        return openssl_pkey_get_private($this->privateKey, $this->privateKeyPassword) !== false;
    }

    /**
     * @throws SignerException
     */
    private function getPrivateKeyResource(): \OpenSSLAsymmetricKey
    {
        if ($this->privateKeyResource ?? null) {
            return $this->privateKeyResource;
        }

        $privateKeyResource = openssl_pkey_get_private($this->privateKey, $this->privateKeyPassword);
        if (!$privateKeyResource) {
            throw new SignerException('Given key is not valid PEM private key, or passphrase is incorrect.');
        }

        $this->privateKeyResource = $privateKeyResource;

        return $this->privateKeyResource;
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @throws SignerException
     */
    public function sign(array $params): string
    {
        $digestText = implode('|', $params);
        if (!openssl_sign($digestText, $digest, $this->getPrivateKeyResource())) {
            throw new SignerException('Failed to sign the data.');
        }
        assert(is_string($digest));

        return base64_encode($digest);
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @throws SignerException
     */
    public function verify(
        array $params,
        string $digest,
    ): bool {
        $data = implode('|', $params);
        $digest = base64_decode($digest);
        assert($digest !== false);

        $result = openssl_verify($data, $digest, $this->getPublicKeyResource());

        if ($result !== 1) {
            throw new SignerException('Digest is not correct!');
        }

        return true;
    }

    /**
     * @throws SignerException
     */
    private function getPublicKeyResource(): \OpenSSLAsymmetricKey
    {
        if ($this->publicKeyResource) {
            return $this->publicKeyResource;
        }

        if (!file_exists($this->publicKey) || !is_readable($this->publicKey)) {
            throw new SignerException("Public key ({$this->publicKey}) not exists or not readable!");
        }

        $fp = fopen($this->publicKey, 'rb');
        assert($fp !== false);
        $filesize = filesize($this->publicKey);
        assert($filesize !== false && $filesize > 0);
        $key = fread($fp, $filesize);
        fclose($fp);
        assert($key !== false);

        $publicKeyResource = openssl_pkey_get_public($key);
        if (!$publicKeyResource) {
            throw new SignerException("'{$this->publicKey}' is not valid PEM public key.");
        }
        $this->publicKeyResource = $publicKeyResource;

        return $this->publicKeyResource;
    }
}
