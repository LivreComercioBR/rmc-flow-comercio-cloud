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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercado_pago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        'notification_url' => env('MERCADO_PAGO_NOTIFICATION_URL'),
        'activate_approved_payments' => env('MERCADO_PAGO_ACTIVATE_APPROVED_PAYMENTS', false),
        'checkout_enabled' => env('MERCADO_PAGO_CHECKOUT_ENABLED', false),
        'sandbox' => env('MERCADO_PAGO_SANDBOX', true),
        'plan_code' => env('MERCADO_PAGO_PLAN_CODE', 'comercio-cloud-essencial'),
    ],
    'subscription_panel' => [
        'url' => env('SUBSCRIPTION_PANEL_URL'),
        'product_code' => env('SUBSCRIPTION_PANEL_PRODUCT_CODE', 'rmc-flow-comercio-cloud'),
        'secret' => env('SUBSCRIPTION_PANEL_SECRET'),
    ],
    'support' => [
        'email' => env('SUPPORT_EMAIL', 'contato@rmcsites.com.br'),
        'whatsapp' => env('SUPPORT_WHATSAPP', '5519990136498'),
    ],

];
