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

    'iae_central' => [
        'base_url' => env('IAE_CLOUD_URL', 'https://iae-sso.virtualfri.id'),
        'token_path' => env('IAE_TOKEN_PATH', '/api/v1/auth/token'),
        'soap_audit_path' => env('IAE_SOAP_AUDIT_PATH', '/soap/v1/audit'),
        'rabbitmq_publish_path' => env('IAE_RABBITMQ_PUBLISH_PATH', '/api/v1/messages/publish'),
        'api_key' => env('IAE_CLOUD_API_KEY'),
        'm2m_token' => env('IAE_M2M_TOKEN'),
        'client_id' => env('IAE_CLIENT_ID'),
        'client_secret' => env('IAE_CLIENT_SECRET'),
        'team_id' => env('IAE_TEAM_ID', 'TEAM-07'),
        'exchange' => env('IAE_EXCHANGE', 'iae.central.exchange'),
    ],

];
