<?php

use Ryra\Modules\Example\Controllers\ExampleController;
use Ryra\Modules\Example\Providers\ExampleServiceProvider;

return [
    'name' => 'Example',

    'controllers' => [
        ExampleController::class,
    ],

    'providers' => [
        ExampleServiceProvider::class,
    ],
];
