<?php

declare(strict_types=1);

use Behat\Config\Config;

return (new Config())
    ->import([
        'suites/select_payment_method_checkout.php',
        'suites/adding_payment_method.php',
        'suites/payment_request_processing.php',
    ]);
