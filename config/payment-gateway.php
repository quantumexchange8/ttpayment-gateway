<?php

return [
    'local' => [
        'paymentUrl' => 'http://127.0.0.1:8010/',
        'appId' => 'metafinx',
        'merchantId' => '1',
        'returnUrl' => 'updateDeposit',
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
    'robotec_live' => [
        'paymentUrl' => 'https://app.robotec.live/',
        'appId' => 'robotec',
        'merchantId' => '1',
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