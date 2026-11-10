<?php

use Ryra\Core\Image\Modifiers\Cover;
use Ryra\Core\Image\Modifiers\ToWebp;

return [
    'driver' => \Intervention\Image\Drivers\Gd\Driver::class,

    'options' => [],

    'styles' => [
        'cover_1920x526' => [
            Cover::class => ['width' => 1920, 'height' => 526],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ]
    ]
];
