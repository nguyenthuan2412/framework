<?php

return [
    'dir_name' => storage_path(join_paths('public', 'sitemap.xml')),

    'max_urls' => 5000,

    'pretty' => false,

    'urls' => [
        // [
        //     'loc' => env('URL_FRONT_END') . '/en',
        //     'changefreq' => Frequency::DAILY,
        //     'priority' => 1.0,
        //     'languages' => [
        //         'x-default' => env('URL_FRONT_END') . '/en',
        //         'en' => env('URL_FRONT_END') . '/en',
        //         'vi' => env('URL_FRONT_END') . '/vi',
        //     ],
        //     'lastmod' => time()
        // ],
    ],

    'dynamic' => [
    ]
];