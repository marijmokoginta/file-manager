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
    ]
];
