<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALLBACK'),
    ],
    'msal_ad' => [
        'cloud_url' => env('AZURE_CLOUD_URL'),
        'client_id' => env('AZURE_CLIENT_ID'),
        'tenant_id' => env('AZURE_TENANT_ID'),
        'secret_value' => env('AZURE_SECRET_VALUE'),
    ],
    'shopify' => [
        'store_name' => env('SHOPIFY_STORE_NAME'),
        'client_id' => env('SHOPIFY_APP_ID'),
        'url' => env('SHOPIFY_URL'),
        'access_token' => env('SHOPIFY_ACCESS_TOKEN')
    ],
    'intellicare' => [
        'url' => env('INTELLICARE_API_URL'),
        'username' => env('INTELLICARE_API_USERNAME'),
        'password' => env('INTELLICARE_API_PASSWORD'),
        'aes_key' => env('INTELLICARE_AES_KEY')
    ]

];

// <a href="https://a1-integ-fe-385139891106.asia-southeast1.run.app/checkout?cartId={{ cart.token }}">Check Out</a>
