<?php

return [
    // Enable or disable API documentation
    'enabled' => env('API_DOCS_ENABLED', true),

    // API Information
    'title' => env('API_DOCS_TITLE', 'API Documentation của hệ thống xlooca.'),

    'description' => env('API_DOCS_DESCRIPTION', 'API documentation được tự động tạo từ controller và ValidateDTO'),

    'version' => env('API_DOCS_VERSION', '1.0.0'),

    // API Servers
    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => 'API Server'
        ],
    ],

    // API Docs URL
    'docs_url' => env('API_DOCS_URL', '/api-docs'),

    'basic_auth' => [
        'enable' => true,

        'account' => [
            'username' => 'docs-api-itourism@vietiso.com',
            'password' => 'j12i31i2asd9cua98djaid12312i3jasjda'
        ]
    ]
];