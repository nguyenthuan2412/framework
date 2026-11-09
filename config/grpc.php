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
    | Build (google/protobuf + protoc)
    |--------------------------------------------------------------------------
    |
    | `php ryra grpc:generate` sinh .proto từ class rồi dùng protoc để sinh class
    | google/protobuf vào build_path. Chạy lại lệnh này mỗi khi sửa message/service.
    |
    */

    'protoc' => env('GRPC_PROTOC', 'protoc'),

    'build_path' => storage_path('grpc'),

    // Namespace gốc cho class protoc sinh ra từ DTO.
    'namespace' => 'GrpcStub',

    // Thư mục chứa .proto có sẵn (của team khác) cần biên dịch thêm để dùng trực tiếp class protoc.
    'proto_paths' => [],

    // Thư mục include thêm cho protoc.
    'include_paths' => [],

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
