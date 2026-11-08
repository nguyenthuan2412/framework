<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

return [
    'default' => [
        'handlers' => [
            [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => storage_path(join_paths('vietiso', 'logs', 'vietiso.log')),
                    'level' => Level::Debug,
                ],
                'formatter' => [
                    'class' => LineFormatter::class,
                    'constructor' => [
                        'format' => null,
                        'dateFormat' => null,
                        'allowInlineLineBreaks' => true,
                        'ignoreEmptyContextAndExtra' => true
                    ],
                ],
            ],
        ],
        'processors' => [
            
        ],
    ],
];