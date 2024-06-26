<?php

return [
    'staging' => [
        'paymentUrl' => 'http://127.0.0.1:8010/',
        'apiKey' => 'b2680272-958b-41a1-bd97-d52ad9fcbd41',
        'appId' => 'metafinx',
        'endPoint' => 'https://apilist.tronscanapi.com/api/contracts/smart-contract-triggers-batch?fields=hash,method',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
    'testLive' => [
        'paymentUrl' => '',
        // 'apiKey' => 'b2680272-958b-41a1-bd97-d52ad9fcbd41',
        'appId' => 'metafinx',
        'endPoint' => 'https://apilist.tronscanapi.com/api/contracts/smart-contract-triggers-batch?fields=hash,method',
        'merchantId' => '1',
        'returnUrl' => 'testing_payment/depositReturn',
        'callBackUrl' => 'testing_payment/depositCallback',
    ],
    'live' => [
        'apiKey' => 'b2680272-958b-41a1-bd97-d52ad9fcbd41',
        'appId' => 'metafinx',
    ],
];