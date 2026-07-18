<?php

declare(strict_types=1);

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_HOST') ?: 'mysql',
            'port'        => (int) (getenv('DB_PORT') ?: 3306),
            'database'    => getenv('DB_DATABASE') ?: 'english_memory',
            'username'    => getenv('DB_USERNAME') ?: 'root',
            'password'    => getenv('DB_PASSWORD') ?: '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'     => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
