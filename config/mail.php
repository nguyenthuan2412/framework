<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cấu hình phương thức gửi mail mặc định
    |--------------------------------------------------------------------------
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    */

    'mailers' => [

        'smtp' => [
            'scheme' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'options' => [
                'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            ]
        ],

        'sendgrid' => [
            'scheme' => 'sendgrid+api',
            'key' => env('MAIL_SEND_GRID_API_KEY'),
            'region' => 'us'
        ],

        'mailtrap' => [
            'scheme' => 'mailtrap+api',
            'host' => env('MAIL_HOST', 'sandbox.smtp.mailtrap.io'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD')
        ],

        'roundrobin' => [
            'smtp',
            'mailtrap',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail mặc định
    |--------------------------------------------------------------------------
    |
    | Nếu bạn muốn tất cả email gửi từ ứng dụng của bạn được gửi từ cùng một
    | địa chỉ. Ở đây bạn có thể chỉ định tên và địa chỉ email của bạn.
    | Trong trường hợp bạn muốn gửi từ một mail khác bạn vẫn có thể thêm
    | vào trong hàm gửi mail.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
];