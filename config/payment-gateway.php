<?php

return [
    'local' => [
        'paymentUrl' => 'http://127.0.0.1:8010/dashboard',
        'appId' => 'metafinx',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
    'metafinx' => [
        'paymentUrl' => 'https://metafinx-member.currenttech.pro/',
        'appId' => 'metafinx',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
    'robotec' => [
        'paymentUrl' => 'https://robotec-user.currenttech.pro/',
        'appId' => 'robotec',
        'merchantId' => '10',
        'returnUrl' => 'deposit_return',
        'callBackUrl' => 'deposit_callback',
    ],
    'live' => [
        'paymentUrl' => 'https://metafinx-member.currenttech.pro/',
        'appId' => 'metafinx',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
];