<?php

use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\Infrastructure\UrlGenerator\LocalFileUrlGenerator;

return [
    'default_driver' => env('FILE_MANAGER_DRIVER', 'local'),

    'drivers' => [
        'local' => [
            'class' => LocalFileSaver::class,
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