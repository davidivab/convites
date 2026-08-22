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

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (P42)
    |--------------------------------------------------------------------------
    | `redirect` DEBE apuntar al backend (este mismo), nunca al front — Google
    | no conoce el front. Tras el callback, el backend redirige al front con
    | un código de intercambio de un solo uso (ver GoogleAuthController).
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // A dónde redirigir al navegador con el código de intercambio.
        'frontend_callback_url' => env('GOOGLE_FRONTEND_CALLBACK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot WhatsApp / MCP (solo lectura)
    |--------------------------------------------------------------------------
    | Token Bearer distinto de Sanctum. Usado por n8n tools → /api/bot/v1.
    */
    'bot' => [
        'token' => env('CONVITES_BOT_TOKEN'),
        // Primer origen de FRONTEND_URL si no hay override dedicado.
        'frontend_url' => env('CONVITES_BOT_FRONTEND_URL', env('FRONTEND_URL', 'https://convites.co')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nominatim (OpenStreetMap geocoding)
    |--------------------------------------------------------------------------
    */
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'Convites/1.0 (https://convites.co; geo@convites.co)'),
        // minLng,minLat,maxLng,maxLat — foco Risaralda
        'viewbox' => env('NOMINATIM_VIEWBOX', '-76.20,4.50,-75.30,5.20'),
    ],

];
