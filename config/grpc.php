<?php

return [

    /*
    |--------------------------------------------------------------------------
    | gRPC server
    |--------------------------------------------------------------------------
    |
    | gRPC chạy chung port với HTTP server (HTTP/2 phải được bật bằng
    | OPTION_OPEN_HTTP2_PROTOCOL trong config/app.php). Request có
    | content-type application/grpc sẽ được chuyển cho gRPC kernel.
    |
    */

    'enabled' => env('GRPC_ENABLED', true),

    // Service đăng ký toàn cục (ngoài các service khai báo trong *.module.php qua key `grpc_services`).
    'services' => [],

    // Message không gắn với service nào nhưng vẫn muốn sinh ra .proto.
    'messages' => [],

    // Interceptor chạy cho mọi rpc, class implement Ryra\Core\Grpc\Server\Interceptor.
    'interceptors' => [],

    // Bật service chuẩn grpc.health.v1.Health.
    'health' => true,

    /*
    |--------------------------------------------------------------------------
    | Sinh DTO từ .proto (`php ryra grpc:dto`)
    |--------------------------------------------------------------------------
    |
    | Dùng .proto có sẵn (vd của service khác): sinh class #[Message], enum, interface
    | #[Service] vào module. Không cần protoc hay google/protobuf.
    |
    */

    'dto' => [
        // Thư mục tìm file import (ngoài thư mục của file đang import và thư mục gốc project).
        'include_paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Xuất .proto (`php ryra grpc:proto`)
    |--------------------------------------------------------------------------
    */

    'proto' => [
        'output' => base_path('proto'),

        // File option thêm vào mọi file, vd: 'go_package' => 'example.com/gen/pb'
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    */

    'default_client' => 'default',

    'clients' => [
        'default' => [
            'address' => env('GRPC_CLIENT_ADDRESS', '127.0.0.1:' . env('APP_PORT', 9501)),
            'ssl' => false,
            'timeout' => 5.0,
            'connect_timeout' => 3.0,
            'max_connections' => 16,
            'metadata' => [],
        ],
    ],
];
