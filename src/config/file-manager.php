<?php

return [
    'default_driver' => env('FILE_MANAGER_DRIVER', 'local'),

    'drivers' => [
        'local' => [
            'class' => \M2code\FileManager\Drivers\Local\LocalFileSaver::class,
            'disk' => 'public'
        ]
    ]
];