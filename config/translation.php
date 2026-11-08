<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Translation Driver
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này xác định driver translation mặc định mà framework sử dụng.
    | Các driver được hỗ trợ: "array", "json", "database"
    |
    | - array: Load từ file PHP (lang/vi/messages.php)
    | - json: Load từ file JSON (lang/vi/messages.json)
    | - database: Load từ bảng translations trong database
    |
    */
    'driver' => env('TRANSLATION_DRIVER', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    |
    | Locale mặc định của ứng dụng. Locale này sẽ được sử dụng khi không có
    | locale nào được chỉ định trong Context.
    |
    */
    'locale' => env('APP_LOCALE', 'vi'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | Fallback locale sẽ được sử dụng khi translation không tồn tại trong
    | locale hiện tại. Ví dụ: nếu không tìm thấy translation tiếng Việt,
    | hệ thống sẽ thử tìm trong tiếng Anh.
    |
    */
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | Danh sách các locale được hỗ trợ trong ứng dụng. Dùng để validate
    | và hiển thị danh sách ngôn ngữ cho người dùng chọn.
    |
    */
    'available_locales' => ['vi', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Translation Paths
    |--------------------------------------------------------------------------
    |
    | Các đường dẫn chứa file translation. Framework sẽ tìm kiếm translation
    | trong các thư mục này theo thứ tự.
    |
    */
    'paths' => [
        base_path('lang'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Cấu hình chi tiết cho từng driver. Mỗi driver có thể có các tùy chọn
    | riêng như connection, table name, v.v.
    |
    */
    'drivers' => [
        'database' => [
            'connection' => env('DB_CONNECTION'),
            'table' => 'translations',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Cache
    |--------------------------------------------------------------------------
    |
    | Bật caching để cải thiện hiệu suất. Khuyến nghị bật trong môi trường
    | production. Cache sẽ được tự động invalidate khi thay đổi translation.
    |
    | driver: Cache driver sử dụng (null = mặc định từ config/cache.php)
    |         Các driver: "file", "database", "redis", etc.
    |
    */
    'cache' => [
        'enabled' => env('TRANSLATION_CACHE', false),
        'driver' => env('TRANSLATION_CACHE_DRIVER'),  // null = default cache driver
        'ttl' => env('TRANSLATION_CACHE_TTL', 3600),  // 1 hour
        'prefix' => 'trans:',
    ],

];
