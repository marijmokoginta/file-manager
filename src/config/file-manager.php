<?php

use M2code\FileManager\Drivers\Local\LocalFileSaver;

return [
    'default_driver' => env('FILE_MANAGER_DRIVER', 'local'),

    'drivers' => [
        'local' => [
            'class' => LocalFileSaver::class,
            'disk' => 'public'
        ]
    ]
];