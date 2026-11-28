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

    'fast2sms' => [
        'api_key' => env('FAST2SMS_API_KEY'),
        'route' => env('FAST2SMS_ROUTE', 'otp'),
    ],

    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID'),

        // Optional explicit audience (the OAuth client id, e.g.
        // 627768077846-xxxx.apps.googleusercontent.com). When empty, the token
        // audience must belong to `project_id`.
        'client_id' => env('FIREBASE_CLIENT_ID'),

        // Accept a posted firebase_id with no ID token. Convenient for the local
        // demo flow, dangerous in production: it lets anyone claim any account.
        'allow_id_only' => (bool) env(
            'FIREBASE_ALLOW_ID_ONLY',
            env('APP_ENV', 'production') !== 'production'
        ),

        'tokeninfo_url' => env('FIREBASE_TOKENINFO_URL', 'https://oauth2.googleapis.com/tokeninfo'),
        'certs_url' => env(
            'FIREBASE_CERTS_URL',
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'
        ),
    ],

    'clerk' => [
        'publishable_key' => env('CLERK_PUBLISHABLE_KEY'),
        'secret_key' => env('CLERK_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenStreetMap (Nominatim geocoding + OSRM routing)
    |--------------------------------------------------------------------------
    |
    | Both public instances are free but rate-limited, so results are cached and
    | requests are throttled. Point the URLs at a self-hosted instance (or a
    | commercial Nominatim/OSRM provider) for production traffic; the public
    | Nominatim usage policy forbids heavy use.
    |
    */

    'osm' => [
        'enabled' => (bool) env('OSM_ENABLED', true),
        'nominatim_url' => env('OSM_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'osrm_url' => env('OSM_OSRM_URL', 'https://router.project-osrm.org'),
        'user_agent' => env('OSM_USER_AGENT', 'IntercityWheels/2.0 (+https://intercitywheels.com)'),
        'country_codes' => env('OSM_COUNTRY_CODES', 'in'),
        'timeout' => (float) env('OSM_TIMEOUT', 3.0),
        'throttle' => (bool) env('OSM_THROTTLE', true),
        'cache_ttl' => (int) env('OSM_CACHE_TTL', 604800),
        'route_cache_ttl' => (int) env('OSM_ROUTE_CACHE_TTL', 43200),
        'db_cache' => (bool) env('OSM_DB_CACHE', true),
        'route_ttl_days' => (int) env('OSM_ROUTE_TTL_DAYS', 30),
        'place_ttl_days' => (int) env('OSM_PLACE_TTL_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Network IP Geolocation (approximate initial pickup detection)
    |--------------------------------------------------------------------------
    */

    'geoip' => [
        'enabled' => (bool) env('GEOIP_ENABLED', true),
        'url' => env('GEOIP_URL', 'https://ipwho.is'),
        'timeout' => (float) env('GEOIP_TIMEOUT', 3.0),
    ],

];
