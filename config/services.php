<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
        'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com/v19.0'),
    ],

    // PayHere (https://support.payhere.lk). Without a merchant ID and secret a
    // mock gateway is used: local runs never reach PayHere. The App ID / secret
    // (an API key with Automated Charging, Payment Retrieval and Refund) charge
    // saved cards, confirm payments and refund them.
    'payhere' => [
        'merchant_id' => env('PAYHERE_MERCHANT_ID'),
        'merchant_secret' => env('PAYHERE_MERCHANT_SECRET'),
        'app_id' => env('PAYHERE_APP_ID'),
        'app_secret' => env('PAYHERE_APP_SECRET'),
        'sandbox' => (bool) env('PAYHERE_SANDBOX', true),
        // Where PayHere posts payment results; defaults to the webhook route.
        // Set it to a tunnel (https://….trycloudflare.com/webhooks/payhere) to test the sandbox locally.
        'notify_url' => env('PAYHERE_NOTIFY_URL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
