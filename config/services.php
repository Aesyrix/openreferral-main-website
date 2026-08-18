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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Firebase Cloud Messaging — push for the Flutter agent app (Android + APNs).
    | Prefer the HTTP v1 API: point FCM_CREDENTIALS at a service-account JSON
    | file (path relative to the project root is fine). FCM_SERVER_KEY is the
    | legacy fallback. With neither set, pushes are logged and skipped.
    */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
        'server_key' => env('FCM_SERVER_KEY'),
    ],

];
