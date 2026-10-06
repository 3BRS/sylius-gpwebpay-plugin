<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\When;
use Tests\ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Behat\Pages\Admin\PaymentMethod\EditPageInterface;

final readonly class ManagingPaymentMethodsContext implements Context
{
    public function __construct(
        private EditPageInterface $updatePage,
    ) {
    }

    #[When('I configure it with test GP webpay credentials')]
    public function iConfigureItWithTestGPWebpayCredentials(): void
    {
        $this->updatePage->setGPWebpayMerchantNumber('TEST');
        $this->updatePage->setGPWebpayKeyPassword('TEST');
        $this->updatePage->setGPWebpayKey('-----BEGIN ENCRYPTED PRIVATE KEY-----
MIIC3TBXBgkqhkiG9w0BBQ0wSjApBgkqhkiG9w0BBQwwHAQI6iupb4a5ItcCAggA
MAwGCCqGSIb3DQIJBQAwHQYJYIZIAWUDBAECBBCEJC3II+XVJBZM5pmY2hYCBIIC
gHnLCEfWl3DE/M8cjz/aONSpWOjKHBgL/9n8lN172jWwJS6dgUEGXwqWKDVk3VBR
csSacUwl2DP6tRfuBomHTHt70zLGe/fu6EihvblINFjL9V+MuFDzjBC16LXdEzpo
v7v6a+8bhFK+ERTx2sDizrq+787Z2vRgJxU3+VzQG6l9q4/89Ja2HNKRP/tgCgDY
vTYrMqn5Mht0CmIUDCFm2jlWnN2ClJWAKLSb9Q/ngboE90T9p16U3GrhkrTEfNZn
ySYz8Fs99jjs7tncLGlj3eKjG+4DAhF3YqFzs45L4G8WVQ/V2Pg5gjgaYqFyDxZ6
5Y5jW8bIDv5BPoTAOOGWRFVYR8qbROLRdvpgcNsqYAGMKDB+pfSeTs64hWdEC2sZ
6p78kGBJVotowcuZ3K1sW3JLW8FYGTcGcLkA0K5+36Jqe2yobdC9Fs9ddI0Twcif
QTBlDU7JuT9yBw0PlMwwpd48KWdPy0cz3PTZK+OQ/Kl1kBHxcU2mL7Gtz0xz5B88
mXyuDLLcpCSIfofjvTetrWoXkIoo+JJcZ6hGyVlP2mgaoDZ9VpkS/4O1aNtmvThU
ZchhspgcgycSltJvsXs+xM9cTo2oWYffoCW8vZw2dkyFtCZXHC+ZlOQXIAMIdG4T
anXKx0ZmdiN1lJbOulAk9eeg+bHPGUeeVF0w6G4XPKGVAM36yByxYBlACy9rhmGC
Lapmv7Y6QNyU6sRy5p8NXV6U3IsieZ51NofSjVSfNK3JQH/D1MKoJnFaJsc/nMJE
tKGM7A7fSWY8OAg/UFvOEyzauq4n2N5ZS3fSvb7DRZHT0XslwA9BZzkcN3MwycYV
BlI7U7oBKYB5+tzNd+HM/5M=
-----END ENCRYPTED PRIVATE KEY-----
');
    }
}
