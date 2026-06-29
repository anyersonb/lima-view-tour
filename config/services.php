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

    'culqi' => [
        'public_key'     => env('CULQI_PUBLIC_KEY'),
        'secret_key'     => env('CULQI_SECRET_KEY'),
        'webhook_secret' => env('CULQI_WEBHOOK_SECRET'),
        'api_url'        => env('CULQI_API_URL', 'https://api.culqi.com/v2'),
        'env'            => env('CULQI_ENV', 'sandbox'),
    ],

    'paypal' => [
        'client_id'  => env('PAYPAL_CLIENT_ID'),
        'secret'     => env('PAYPAL_SECRET'),
        'mode'       => env('PAYPAL_MODE', 'sandbox'), // sandbox|live
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'place_id'     => env('GOOGLE_PLACE_ID'),
    ],

    'tripadvisor' => [
        'api_key'     => env('TRIPADVISOR_API_KEY'),
        'location_id' => env('TRIPADVISOR_LOCATION_ID'),
    ],

    'recaptcha' => [
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'version'    => env('RECAPTCHA_VERSION', 'v3'),   // v2 | v3
        'threshold'  => env('RECAPTCHA_V3_THRESHOLD', 0.5),
        'enabled'    => env('RECAPTCHA_ENABLED', false),
    ],

];
