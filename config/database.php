<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tên kết nối cơ sở dữ liệu mặc định
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể chỉ định kết nối cơ sở dữ liệu nào bên dưới mà bạn muốn
    | để sử dụng làm kết nối mặc định cho các hoạt động cơ sở dữ liệu.
    | Đây là kết nối sẽ được sử dụng trừ khi không có kết nối khác
    | được chỉ định rõ ràng khi bạn thực hiện một truy vấn hoặc một câu lệnh.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Kết nối cơ sở dữ liệu
    |--------------------------------------------------------------------------
    |
    | Dưới đây là danh sách các driver đang hỗ trợ.
    |
    */

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'vietiso'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => 'default_',
            'options' => [
                \PDO::ATTR_TIMEOUT => 10,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode=(SELECT REPLACE(@@sql_mode, "ONLY_FULL_GROUP_BY", ""))',
            ],
            'pool_options' => [
                'min_connections' => 1,
                'max_connections' => 15,
                'wait_timeout' => 3,
                'heartbeat' => 60,
                'max_idle_time' => 60
            ]
        ],

        'mongodb' => [
            'driver' => 'mongodb',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connections
    |--------------------------------------------------------------------------
    |
    | Redis là một hệ thống lưu trữ key-value mã nguồn mở, nhanh và tiên tiến.
    | Nó cũng cung cấp nhiều cấu trúc dữ liệu phong phú hơn như strings, hashes,
    | lists, sets, và sorted sets.
    |
    | Client hỗ trợ: "phpredis" (ext-redis), "predis" (predis/predis package)
    |
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'prefix' => env('REDIS_PREFIX', ''),
        ],

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_DB', 0),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 60.0),
            'timeout' => (float) env('REDIS_TIMEOUT', 0.0),
            'persistent' => (bool) env('REDIS_PERSISTENT', false),
            'pool_options' => [
                'min_connections' => (int) env('REDIS_POOL_MIN', 1),
                'max_connections' => (int) env('REDIS_POOL_MAX', 10),
                'wait_timeout' => (float) env('REDIS_POOL_WAIT_TIMEOUT', 3.0),
                'heartbeat' => (float) env('REDIS_POOL_HEARTBEAT', -1),
                'max_idle_time' => (float) env('REDIS_POOL_MAX_IDLE_TIME', 60.0),
            ],
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_CACHE_DB', 1),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 60.0),
            'timeout' => (float) env('REDIS_TIMEOUT', 0.0),
            'persistent' => (bool) env('REDIS_PERSISTENT', false),
        ],

        'session' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_SESSION_DB', 2),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 60.0),
            'timeout' => (float) env('REDIS_TIMEOUT', 0.0),
            'persistent' => (bool) env('REDIS_PERSISTENT', false),
        ],

        'queue' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_QUEUE_DB', 3),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 60.0),
            'timeout' => (float) env('REDIS_TIMEOUT', 0.0),
            'persistent' => (bool) env('REDIS_PERSISTENT', false),
        ],

    ],
];