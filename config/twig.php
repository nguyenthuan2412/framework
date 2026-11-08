<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cấu hình template engine
    |--------------------------------------------------------------------------
    |
    | Mặc định template sẽ được lưu trữ trong folder views của các modules. 
    |
    */

    'options' => [
        // Kích hoạt chế độ debug
        'debug' => env('TWIG_DEBUG', true),

        // Đường dẫn đến thư mục cache, có thể bỏ qua nếu không muốn dùng cache
        'cache' => storage_path(join_paths('cache', 'twig')),

        // Tự động tải lại các template khi chúng thay đổi
        'auto_reload' => env('TWIG_AUTO_RELOAD', true),

        // Gây lỗi khi sử dụng biến không tồn tại trong template
        'strict_variables' => env('TWIG_STRICT_VARIABLES', true),

        // Tự động thoát các biến trong template để ngăn chặn XSS
        'autoescape' => env('TWIG_AUTOESCAPE', 'html'),
    ],

    // Các extension của Twig
    'extensions' => [
        \Twig\Extension\DebugExtension::class,
        // Có thể thêm các extension khác tại đây
    ],

    // Global variables, có thể thêm các biến toàn cục mà bạn muốn sử dụng trong tất cả các template
    'globals' => [
        'app_name' => env('APP_NAME', 'Ryra'),
        'env' => env('APP_ENV', 'production'),
    ],
];
