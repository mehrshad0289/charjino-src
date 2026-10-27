<?php

return [
    'driver' => env('PAYMENT_DRIVER', env('APP_ENV') === 'testing' ? 'mock' : 'zibal'),
    'callback_url' => env('PAYMENT_CALLBACK_URL', ''),
    'min_rial' => 10_000,
    'max_rial' => 1_000_000_000,
    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID', ''),
        'sandbox' => filter_var(env('ZARINPAL_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('ZARINPAL_BASE_URL', 'https://payment.zarinpal.com/pg/v4/payment/'),
        'sandbox_base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment/',
        'start_pay_url' => env('ZARINPAL_START_PAY_URL', 'https://payment.zarinpal.com/pg/StartPay/'),
        'sandbox_start_pay_url' => 'https://sandbox.zarinpal.com/pg/StartPay/',
        'connect_timeout' => 5,
        'timeout' => 15,
    ],
];
