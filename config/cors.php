<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tùy chọn CORS
    |--------------------------------------------------------------------------
    |
    | Các tùy chọn allowed_methods (phương thức cho phép) và allowed_headers (header cho phép)
    | không phân biệt chữ hoa chữ thường.
    |
    | Bạn không cần phải khai báo cả allowed_origins và allowed_origins_patterns.
    | Chỉ cần một trong hai khớp thì origin đó được xem là hợp lệ.
    |
    */

    /*
     * Bạn có thể bật CORS cho 1 hoặc nhiều đường dẫn.
     * Ví dụ: ['api/*']
     */
    'paths' => ['*'],

    /*
    * Khớp với phương thức của request.
    */
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS'
    ],

    /*
     * Khớp với origin của request. `['*']` cho phép tất cả origin.
     * Có thể dùng ký tự đại diện (wildcard), ví dụ `*.mydomain.com`
     */
    'allowed_origins' => [
        'http://localhost:9091',
        'http://localhost:6060',
        'http://localhost:3000',
        'http://localhost:5053',
        'https://web.xlooca.com',
        'https://hub25.xlooca.com',
        'https://xtickets.xlooca.com',
        'https://visitvietnam.org.vn',
    ],

    /*
     * Các mẫu (pattern) có thể dùng với `preg_match` để khớp với origin.
     */
    'allowed_origins_patterns' => [],

    /*
     * Thiết lập header phản hồi Access-Control-Allow-Headers.
     * `['*']` cho phép tất cả header.
     */
    'allowed_headers' => [
        'X-Requested-With',
        'Authorization',
        'Content-Type',
        'Pos-Token'
    ],

    /*
     * Thiết lập header phản hồi Access-Control-Expose-Headers với các header này.
     */
    'exposed_headers' => [],

    /*
     * Thiết lập header phản hồi Access-Control-Max-Age khi giá trị > 0.
     */
    'max_age' => 86400,

    /*
     * Thiết lập header Access-Control-Allow-Credentials.
     */
    'supports_credentials' => true,
];
