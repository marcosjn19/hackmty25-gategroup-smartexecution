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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'inference' => [
        'base' => env('INFERENCE_API_BASE'),
        'timeout' => env('INFERENCE_API_TIMEOUT', 25),
        'verify' => env('INFERENCE_TLS_VERIFY', true),
        'api_key' => env('INFERENCE_API_KEY'),
        'basic_user' => env('INFERENCE_BASIC_USER'),
        'basic_pass' => env('INFERENCE_BASIC_PASS'),
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

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'flq6f7yk4E4fJM5XTYuZ'), // Rachel - voz femenina multiidioma
    ],

    'flask' => [
        'base_url' => env('FLASK_BASE_URL', 'https://9bfcb5fd3633.ngrok-free.app/'),
    ],


];
