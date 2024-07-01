<?php

return [
    'staging' => [
        'paymentUrl' => 'https://metafinx-member.currenttech.pro/',
        'appId' => 'metafinx',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
    'testLive' => [
        'paymentUrl' => '',
        'appId' => 'metafinx',
        'endPoint' => 'https://apilist.tronscanapi.com/api/contracts/smart-contract-triggers-batch?fields=hash,method',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
    'live' => [
        'appId' => 'metafinx',
    ],
];