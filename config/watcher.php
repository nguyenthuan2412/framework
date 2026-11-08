<?php

return [
    'use_polling' => true,

    // Thời gian lặp khi dùng polling
    'interval' => 400,

    'paths' => [
        [
            'path' => join_paths(base_path('modules')),
            'regex' => [
                '^.+\.php$',
                '^.+\.twig$',
            ]
        ],
        [
            'path' => join_paths(base_path('.env')),
        ],
        [
            'path' => join_paths(base_path('composer.json')),
        ]
    ]
];