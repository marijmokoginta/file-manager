<?php

namespace M2code\FileManager\Core;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use M2code\FileManager\Domain\Contracts\FileSaver;
use RuntimeException;

class FileDriverResolver
{
    /**
     * @throws BindingResolutionException
     */
    public static function resolve(string $contract = FileSaver::class): object
    {
        $drivers = Config::get('file-manager.drivers', []);
        $active = Config::get('file-manager.default_driver', 'local');

        $driverConfig = $drivers[$active] ?? null;

        if (! $driverConfig || ! class_exists($driverConfig['class'])) {
            throw new RuntimeException("FileManager: Invalid driver [$active]");
        }

        $encryptor = App::make(ContentEncryptor::class);

        return app()->make($driverConfig['class'], [
            'config' => $driverConfig,
            'encryptor' => $encryptor,
        ]);
    }
}
