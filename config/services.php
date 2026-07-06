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

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'geocode_url' => env('GOOGLE_MAPS_GEOCODE_URL', 'https://maps.googleapis.com/maps/api/geocode/json'),
    ],

    'openstreetmap' => [
        'reverse_geocode_url' => env('OSM_REVERSE_GEOCODE_URL', 'https://nominatim.openstreetmap.org/reverse'),
    ],

    'firebase' => [
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', public_path('service_file.json')),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'token_uri' => env('FIREBASE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'messaging_base_url' => env('FIREBASE_MESSAGING_BASE_URL', 'https://fcm.googleapis.com/v1/projects'),
    ],

    'stylebite' => [
        'app_store_url' => env('APP_STORE_URL', '#'),
        'play_store_url' => env('PLAY_STORE_URL', '#'),
    ],

];
