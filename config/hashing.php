<?php

return [
    'default' => 'bcrypt',
    
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', PASSWORD_BCRYPT_DEFAULT_COST)
    ],

    'argon' => [
        'memory_cost' => env('MEMORY_COST', PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
        'time_cost' => env('TIME_COST', PASSWORD_ARGON2_DEFAULT_TIME_COST),
        'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
    ],

    'argon2_id' => [
        'memory_cost' => env('MEMORY_COST', PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
        'time_cost' => env('TIME_COST', PASSWORD_ARGON2_DEFAULT_TIME_COST),
        'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
    ]
];