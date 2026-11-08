<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kho lưu trữ Cache mặc định
    |--------------------------------------------------------------------------
    |
    | Tùy chọn này xác định cache store mặc định mà framework sẽ sử dụng. 
    | Kết nối này sẽ được dùng nếu không có cache store nào khác được chỉ định 
    | rõ ràng khi thực hiện một thao tác cache trong ứng dụng.
    |
    */
    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Các kho lưu trữ Cache (Cache Stores)
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể định nghĩa tất cả các "store" cache cho ứng dụng 
    | của mình cũng như driver mà chúng sử dụng. Bạn thậm chí có thể định 
    | nghĩa nhiều store cùng sử dụng một loại driver cache để nhóm các kiểu 
    | dữ liệu khác nhau trong cache.
    |
    | Các driver được hỗ trợ: "database", "file", "memcached", "redis"
    |
    */
    'stores' => [
        'database' => [
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'marshaller' => \Ryra\Core\Cache\Marshallers\SerializeMarshaller::class
        ],

        'file' => [
            'path' => storage_path(join_paths('cache', 'data')),
            'marshaller' => \Ryra\Core\Cache\Marshallers\SerializeMarshaller::class
        ],

        'redis' => [
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'marshaller' => \Ryra\Core\Cache\Marshallers\SerializeMarshaller::class
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiền tố (Prefix) cho Cache Key
    |--------------------------------------------------------------------------
    |
    | Khi sử dụng các cache store như file, database,...
    | có thể sẽ có những ứng dụng khác cũng dùng chung cache. 
    | Vì lý do đó, bạn có thể thêm tiền tố (prefix) cho mỗi cache key 
    | để tránh bị trùng lặp (collision).
    |
    */
    'prefix' => env('CACHE_PREFIX', 'tourdb-'),
];