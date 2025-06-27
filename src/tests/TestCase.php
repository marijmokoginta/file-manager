<?php

namespace M2code\FileManager\tests;

use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\FileManagerServiceProvider;
use \Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FileManagerServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('file-manager', [
            'default_driver' => 'local',
            'drivers' => [
                'local' => [
                    'class' => LocalFileSaver::class,
                    'disk' => 'public'
                ]
            ]
        ]);

        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => __DIR__.'/storage'
        ]);
    }
}