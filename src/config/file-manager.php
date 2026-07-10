<?php

use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\Drivers\Local\LocalFileDeleter;
use M2code\FileManager\Infrastructure\UrlGenerator\LocalFileUrlGenerator;

return [
    'default_driver' => env('FILE_MANAGER_DRIVER', 'local'),

    'drivers' => [
        'local' => [
            'class' => LocalFileSaver::class,
            'disk' => env('FILE_MANAGER_DISK', 'public')
        ]
    ],

    'default_deleter' => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),

    'deleters' => [
        'local' => [
            'class' => LocalFileDeleter::class,
            'disk' => env('FILE_MANAGER_DISK', 'public')
        ]
    ],

    'default_url_generator' => env('FILE_MANAGER_DRIVER', 'local'),

    'url_generators' => [
        'local' => [
            'class' => LocalFileUrlGenerator::class,
            'disk' => env('FILE_MANAGER_DISK', 'public')
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_file_size' => [
            'default'  => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
            'image'    => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
            'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage
    |--------------------------------------------------------------------------
    */
    'tmp' => [
        'disk'     => env('FILE_MANAGER_TMP_DISK', 'local'),
        'prefix'   => env('FILE_MANAGER_TMP_PREFIX', 'tmp/uploads'),
        'lifetime' => env('FILE_MANAGER_TMP_LIFETIME', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    */
    'api' => [
        'token'    => env('FILE_MANAGER_API_TOKEN'),
        'allowed_origins' => explode(',', env('FILE_MANAGER_ALLOWED_ORIGINS', '')),
        'middleware' => env('FILE_MANAGER_API_MIDDLEWARE', 'file-manager.api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Options
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'default_options' => [
            'image' => [
                'optimize'    => true,
                'blurhash'    => true,
                'watermark'   => false,
                'low_quality' => false,
            ],
        ],
        'retry' => [
            'enabled'      => env('FILE_MANAGER_RETRY_ENABLED', true),
            'max_attempts' => env('FILE_MANAGER_RETRY_MAX', 3),
            'delay'        => env('FILE_MANAGER_RETRY_DELAY', 100),
        ],
    ],
];
