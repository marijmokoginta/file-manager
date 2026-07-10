<?php

use M2code\FileManager\Drivers\Local\LocalFileDeleter;
use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\Infrastructure\UrlGenerator\LocalFileUrlGenerator;

return [
    'default_driver' => env('FILE_MANAGER_DRIVER', 'local'),

    'drivers' => [
        'local' => [
            'class' => LocalFileSaver::class,
            'disk' => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    'default_deleter' => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),

    'deleters' => [
        'local' => [
            'class' => LocalFileDeleter::class,
            'disk' => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    'default_url_generator' => env('FILE_MANAGER_DRIVER', 'local'),

    'url_generators' => [
        'local' => [
            'class' => LocalFileUrlGenerator::class,
            'disk' => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_file_size' => [
            'default' => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
            'image' => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
            'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage
    |--------------------------------------------------------------------------
    */
    'tmp' => [
        'disk' => env('FILE_MANAGER_TMP_DISK', 'local'),
        'prefix' => env('FILE_MANAGER_TMP_PREFIX', 'tmp/uploads'),
        'lifetime' => env('FILE_MANAGER_TMP_LIFETIME', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | Enable file content encryption at rest. Supported drivers:
    |   - 'laravel' — uses Laravel's Crypt facade (requires APP_KEY)
    |   - 'openssl' — uses PHP's openssl_encrypt/decrypt (requires encryption.key)
    |
    | When enabled, files are encrypted before writing to disk and decrypted
    | when served via the FileController.
    */
    'encryption' => [
        'enabled' => env('FILE_MANAGER_ENCRYPTION_ENABLED', false),
        'driver' => env('FILE_MANAGER_ENCRYPTION_DRIVER', 'laravel'),
        'key' => env('FILE_MANAGER_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Security
    |--------------------------------------------------------------------------
    |
    | When url.secret is set, file paths in URLs are encrypted using AES-256-CBC
    | instead of plain base64 encoding. URL.base64-encoded paths from previous
    | versions are still accepted (backward compatible).
    */
    'url' => [
        'secret' => env('FILE_MANAGER_URL_SECRET'),
    ],

    'api' => [
        'token' => env('FILE_MANAGER_API_TOKEN'),
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
                'optimize' => true,
                'blurhash' => true,
                'watermark' => false,
                'low_quality' => false,
            ],
        ],
        'retry' => [
            'enabled' => env('FILE_MANAGER_RETRY_ENABLED', true),
            'max_attempts' => env('FILE_MANAGER_RETRY_MAX', 3),
            'delay' => env('FILE_MANAGER_RETRY_DELAY', 100),
        ],
    ],
];
