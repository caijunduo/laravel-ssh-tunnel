<?php

use Illuminate\Support\Str;

return [
    'enabled' => env('SSH_TUNNEL_ENABLED', false),

    /**
     * 支持多路径，以":"隔开
     */
    'bin' => env('SSH_TUNNEL_BIN', 'autossh'),

    'database' => [
        'connections' => [
            'mysql' => 'default',
        ],
        'redis' => [
            'default' => 'default',
            'cache' => 'default',
        ],
    ],

    'tunnel' => [
        'default' => [
            'host' => env('SSH_TUNNEL_HOST'),
            'user' => env('SSH_TUNNEL_USER', 'root'),
            'port' => env('SSH_TUNNEL_PORT', 22),
        ],
    ],

    'temporary' => [
        'prefix' => env('SSH_TUNNEL_TEMPORARY_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '-tunnel'),
        'directory' => env('SSH_TUNNEL_TEMPORARY_DIRECTORY', '/tmp'),
    ],
];
