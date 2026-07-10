<?php

namespace M2code\FileManager\tests;

use M2code\FileManager\Drivers\Local\LocalFileDeleter;
use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\FileManagerServiceProvider;
use M2code\FileManager\Infrastructure\UrlGenerator\LocalFileUrlGenerator;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FileManagerServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('file-manager', [
            'default_driver' => 'local',
            'drivers' => [
                'local' => [
                    'class' => LocalFileSaver::class,
                    'disk' => 'public',
                ],
            ],
            'default_deleter' => 'local',
            'deleters' => [
                'local' => [
                    'class' => LocalFileDeleter::class,
                    'disk' => 'public',
                ],
            ],
            'default_url_generator' => 'local',
            'url_generators' => [
                'local' => [
                    'class' => LocalFileUrlGenerator::class,
                    'disk' => 'public',
                ],
            ],
            'validation' => [
                'max_file_size' => [
                    'default' => 10240,
                    'image' => 10240,
                    'document' => 20480,
                ],
            ],
            'tmp' => [
                'disk' => 'tmp',
                'prefix' => 'tmp/uploads',
                'lifetime' => 86400,
            ],
            'api' => [
                'token' => 'test-token',
                'allowed_origins' => [''],
                'middleware' => 'file-manager.api',
            ],
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
                    'enabled' => false,
                    'max_attempts' => 3,
                    'delay' => 100,
                ],
            ],
        ]);

        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => __DIR__.'/storage/public',
        ]);

        $app['config']->set('filesystems.disks.tmp', [
            'driver' => 'local',
            'root' => __DIR__.'/storage/tmp',
        ]);
    }
}
