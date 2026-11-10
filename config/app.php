<?php

use Swoole\Constant;
use Ryra\Core\App;

return [

    /*
    |--------------------------------------------------------------------------
    | Tên ứng dụng
    |--------------------------------------------------------------------------
    |
    | Tên ứng dụng nó sẽ được sử dụng khi đặt tên trong ứng dụng thông báo
    | hoặc các thành phần giao diên nơi tên ứng dụng cần hiển thị
    |
    */

    'name' => env('APP_NAME', 'Ryra'),

    'version' => env('APP_VERSION', '0.1'),

    /*
    |--------------------------------------------------------------------------
    | Biến môi trường
    |--------------------------------------------------------------------------
    |
    | Giá trị này xác định môi trường mà ứng dụng đang sử dụng đang chạy vào
    | Điều này cho phép developer có thể cấu hình và tuỳ chỉnh cho các môi trường khác nhau.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Chế độ gỡ lỗi
    |--------------------------------------------------------------------------
    |
    | Khi ứng dụng bên chế độ gỡ lỗi, các thông báo lỗi sẽ hiển thị ngay trên giao diện người dùng.
    | Nếu bị tắt một trang lỗi chung chung như 500 sẽ hiển thị ra.
    |
    */

    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Url ứng dụng
    |--------------------------------------------------------------------------
    |
    | Url này sẽ được sử dụng khi khỏi tạo server hay các câu lệnh sau này sẽ được sử dụng.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Múi giờ mặc định ứng dụng
    |--------------------------------------------------------------------------
    |
    | Tại đây có thể chỉ định múi giờ mặc định của ứng dụng của mình.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Cấu hình ngôn ngữ
    |--------------------------------------------------------------------------
    |
    | Tại đây có thể xác định ngôn ngữ mặc định của ứng dụng.
    | Còn fallback_locale sẽ được sử dụng để xác định
    | khi ngôn ngữ chính không có bản dịch thì sẽ sử dụng ở đây.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Khoá mã hoá
    |--------------------------------------------------------------------------
    |
    | Khoá này được sử dụng ở một số nơi như session và cookie.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Cấu hình server swoole
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể chỉ định application sẽ được tạo server nào hiện tại
    | mới hỗ trợ http và websocket. Bạn thể vào website dưới đây để hiểu hơn
    | để cấu hình đúng với nhu cầu của dự án.
    | https://openswoole.com/docs/modules/swoole-http-server-doc
    |
    */

    'swoole' => [
        'default' => 'http',

        'server' => [
            'http' => [
                'driver' => \Ryra\Core\Http\Server::class,
                'host' => env('APP_HOST'),
                'port' => env('APP_PORT'),
                'mode' => SWOOLE_BASE,
                'socket_type' => SWOOLE_SOCK_TCP,

                // Cấu hình chi tiết server
                'configs' => [
                    Constant::OPTION_HOOK_FLAGS => SWOOLE_HOOK_ALL,
                    Constant::OPTION_LOG_FILE => storage_path('/vietiso/logs/swoole.log'),
                    Constant::OPTION_LOG_LEVEL => SWOOLE_LOG_DEBUG,
                    Constant::OPTION_DOCUMENT_ROOT => join_paths(App::root(), 'storage'),
                    Constant::OPTION_ENABLE_STATIC_HANDLER => true,
                    Constant::OPTION_STATIC_HANDLER_LOCATIONS => ['/public'],
                    Constant::OPTION_ENABLE_COROUTINE => true,
                    // Constant::OPTION_WORKER_NUM => swoole_cpu_num(),
                    Constant::OPTION_PID_FILE => storage_path('/vietiso/app.pid'),
                    Constant::OPTION_OPEN_TCP_NODELAY => true,
                    Constant::OPTION_MAX_COROUTINE => 100000,
                    Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
                    Constant::OPTION_MAX_REQUEST => 0,
                    Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
                    Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
                    // Constant::OPTION_TASK_WORKER_NUM => 2,
                    Constant::OPTION_PACKAGE_MAX_LENGTH => 64 * 1024 * 1024, // Tối đa upload 64MB
                ],
            ],

            'websocket' => [
                'driver' => \Ryra\Core\Websocket\Server\Server::class,
                'host' => env('APP_HOST'),
                'port' => env('APP_PORT'),
                'mode' => SWOOLE_BASE,
                'socket_type' => SWOOLE_SOCK_TCP,

                // Cấu hình chi tiết server
                'configs' => [
                    Constant::OPTION_HOOK_FLAGS => SWOOLE_HOOK_ALL,
                    Constant::OPTION_LOG_FILE => storage_path('/vietiso/logs/swoole.log'),
                    Constant::OPTION_LOG_LEVEL => SWOOLE_LOG_DEBUG,
                    Constant::OPTION_DOCUMENT_ROOT => join_paths(App::root(), 'storage'),
                    Constant::OPTION_ENABLE_STATIC_HANDLER => true,
                    Constant::OPTION_STATIC_HANDLER_LOCATIONS => ['/public'],
                    Constant::OPTION_ENABLE_COROUTINE => true,
                    // Constant::OPTION_WORKER_NUM => 2,
                    Constant::OPTION_PID_FILE => storage_path('/vietiso/app.pid'),
                    Constant::OPTION_OPEN_TCP_NODELAY => true,
                    Constant::OPTION_MAX_COROUTINE => 100000,
                    Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
                    Constant::OPTION_MAX_REQUEST => 0,
                    Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
                    Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
                    // Constant::OPTION_TASK_WORKER_NUM => 2,
                    Constant::OPTION_PACKAGE_MAX_LENGTH => 64 * 1024 * 1024, // Tối đa upload 64MB
                ],
            ]
        ]
    ],

    // Cấu hình các proxies tin cậy
    'trusted_proxies' => [
        '127.0.0.1'
    ]
];