<?php

declare(strict_types=1);

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => env('DB_HOST', '127.0.0.1'),
            'hostport' => env('DB_PORT', '3306'),
            'database' => env('DB_NAME', 'zhibo'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASS', 'root'),
            'prefix' => env('DB_PREFIX', ''),
            'charset' => 'utf8mb4',
            'debug' => true,
            'break_reconnect' => true,
        ],
    ],
];
