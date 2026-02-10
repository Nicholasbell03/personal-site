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

    'google' => [
        'youtube_api_key' => env('GOOGLE_YOUTUBE_API_KEY'),
    ],

    'github' => [
        'username' => env('GITHUB_USERNAME'),
        'personal_access_token' => env('GITHUB_PERSONAL_ACCESS_TOKEN'),
    ],

    'embeddings' => [
        'provider' => env('EMBEDDING_PROVIDER', 'openai'),
        'model' => env('EMBEDDING_MODEL', 'text-embedding-3-large'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),
    ],

];
