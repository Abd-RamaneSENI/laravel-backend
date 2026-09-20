<?php

return [
    'environment' => env('PAYMENT_ENV', 'test'),
    'default' => env('PAYMENT_DEFAULT_PROVIDER', 'fake'),
    'currency' => env('PAYMENT_CURRENCY', 'XOF'),
    'timeout' => (int) env('PAYMENT_TIMEOUT_SECONDS', 30),
    'enabled' => array_filter(explode(',', env('PAYMENT_ENABLED_PROVIDERS', 'fake,mtn,moov'))),
    'providers' => [
        'mtn' => [
            'merchant_name' => env('MTN_MERCHANT_NAME', 'SENI-CNF EDU'),
            'test' => ['base_url' => env('MTN_TEST_BASE_URL'), 'api_user' => env('MTN_TEST_API_USER'), 'api_key' => env('MTN_TEST_API_KEY'), 'api_secret' => env('MTN_TEST_API_SECRET'), 'subscription_key' => env('MTN_TEST_SUBSCRIPTION_KEY'), 'target_environment' => env('MTN_TEST_TARGET_ENVIRONMENT', 'sandbox'), 'callback_url' => env('MTN_TEST_CALLBACK_URL'), 'collection_version' => env('MTN_TEST_COLLECTION_VERSION', 'v1_0')],
            'production' => ['base_url' => env('MTN_PROD_BASE_URL'), 'api_user' => env('MTN_PROD_API_USER'), 'api_key' => env('MTN_PROD_API_KEY'), 'api_secret' => env('MTN_PROD_API_SECRET'), 'subscription_key' => env('MTN_PROD_SUBSCRIPTION_KEY'), 'target_environment' => env('MTN_PROD_TARGET_ENVIRONMENT'), 'callback_url' => env('MTN_PROD_CALLBACK_URL'), 'collection_version' => env('MTN_PROD_COLLECTION_VERSION', 'v1_0')],
            'webhook_secret' => env('MTN_WEBHOOK_SECRET'),
        ],
        'moov' => [
            'merchant_number' => env('MOOV_MERCHANT_NUMBER'),
            'merchant_name' => env('MOOV_MERCHANT_NAME', 'SENI-CNF EDU'),
            'test' => ['base_url' => env('MOOV_TEST_BASE_URL'), 'initiate_path' => env('MOOV_TEST_INITIATE_PATH'), 'verify_path' => env('MOOV_TEST_VERIFY_PATH'), 'client_id' => env('MOOV_TEST_CLIENT_ID'), 'client_secret' => env('MOOV_TEST_CLIENT_SECRET'), 'api_key' => env('MOOV_TEST_API_KEY'), 'callback_url' => env('MOOV_TEST_CALLBACK_URL')],
            'production' => ['base_url' => env('MOOV_PROD_BASE_URL'), 'initiate_path' => env('MOOV_PROD_INITIATE_PATH'), 'verify_path' => env('MOOV_PROD_VERIFY_PATH'), 'client_id' => env('MOOV_PROD_CLIENT_ID'), 'client_secret' => env('MOOV_PROD_CLIENT_SECRET'), 'api_key' => env('MOOV_PROD_API_KEY'), 'callback_url' => env('MOOV_PROD_CALLBACK_URL')],
            'webhook_secret' => env('MOOV_WEBHOOK_SECRET'),
        ],
    ],
];
