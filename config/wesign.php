<?php

return [
    'base_url'        => env('WESIGN_BASE_URL', 'https://amisapp.misa.vn/wesign/api/public'),
    'client_id'       => env('WESIGN_CLIENT_ID'),
    'client_secret'   => env('WESIGN_CLIENT_SECRET'),

    'app_code'        => env('WESIGN_APP_CODE', 'VIETISO'),
    'app_name'        => env('WESIGN_APP_NAME', 'VIETISO'),
    'app_subsystem'   => env('WESIGN_APP_SUBSYSTEM', 'Phan he 01'),
    'email_sender'    => env('WESIGN_EMAIL_SENDER', null),

    // endpoints
    'upload_path'     => '/api/File/upload',
    'signflow_path'   => '/Documents/signflow',
    'signflow_status_path' => '/Documents/detail',
    'download_path' => '/api/File/downloadFileInfosByDocumentID',
    'token_endpoint' => '/api/v1/auth/credentials',
    'refresh_token_endpoint' => '/api/v1/auth/refresh-token',
];
