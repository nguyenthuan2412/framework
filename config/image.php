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
        ],
        'cover_534x611' => [
            Cover::class => ['width' => 534, 'height' => 611],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_1120x280' => [
            Cover::class => ['width' => 1120, 'height' => 280],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_1920x600' => [
            Cover::class => ['width' => 1920, 'height' => 600],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_1920x440' => [
            Cover::class => ['width' => 1920, 'height' => 440],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_480x250' => [
            Cover::class => ['width' => 480, 'height' => 250],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_768x400' => [
            Cover::class => ['width' => 768, 'height' => 400],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_1024x534' => [
            Cover::class => ['width' => 1024, 'height' => 534],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_1920x1000' => [
            Cover::class => ['width' => 1920, 'height' => 1000],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_635x400' => [
            Cover::class => ['width' => 635, 'height' => 400],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_406x283' => [
            Cover::class => ['width' => 406, 'height' => 283],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_634x634' => [
            Cover::class => ['width' => 634, 'height' => 634],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
        'cover_313x313' => [
            Cover::class => ['width' => 313, 'height' => 313],
            ToWebp::class => ['quality' => 75, 'strip' => true]
        ],
    ]
];
