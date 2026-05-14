<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'central'),

    'connections' => [
        'central' => [
            'driver'         => 'mysql',
            'host'           => env('DB_CENTRAL_HOST', 'mysql'),
            'port'           => env('DB_CENTRAL_PORT', '3306'),
            'database'       => env('DB_CENTRAL_DATABASE', 'api_kit_central'),
            'username'       => env('DB_CENTRAL_USERNAME', 'api_kit'),
            'password'       => env('DB_CENTRAL_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
        ],

'tenant_template' => [
            'driver'         => 'mysql',
            'host'           => env('DB_TENANT_HOST', 'mysql'),
            'port'           => env('DB_TENANT_PORT', '3306'),
            'database'       => null, // set dynamically by Stancl
            'username'       => env('DB_TENANT_USERNAME', 'api_kit'),
            'password'       => env('DB_TENANT_PASSWORD', ''),
            'unix_socket'    => '',
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client'  => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', 'api_kit_'),
        ],
        'default' => [
            'host'     => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'host'     => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
