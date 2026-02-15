<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    |
    | Applications that may connect to Reverb and receive messages. Each app
    | has a unique ID, key, and secret. The key and secret are used to
    | authenticate connections. Allowed origins restrict which domains
    | may connect when not in production (use * for local development).
    |
    */

    'apps' => [
        [
            'id' => env('REVERB_APP_ID'),
            'name' => env('APP_NAME', 'marcelinos'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'path' => env('REVERB_APP_PATH', ''),
            'capacity' => null,
            'enable_client_messages' => false,
            'enable_usage_metrics' => false,
            'allowed_origins' => array_filter(explode(',', env('REVERB_ALLOWED_ORIGINS', '*'))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    */

    'servers' => [
        [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
        ],
    ],

];
