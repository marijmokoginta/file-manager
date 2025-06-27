<?php

namespace M2code\FileManager\Core;

use http\Exception\RuntimeException;
use Illuminate\Support\Facades\Config;
use M2code\FileManager\Domain\Contracts\FileSaver;

class FileDriverResolver
{
    public static function resolve(string $contract = FileSaver::class): object
    {
        $drivers = Config::get('file-manager.drivers', []);
        $active = Config::get('file-manager.default_driver', 'local');

        $driverConfig = $drivers[$active] ?? null;

        if (!$driverConfig || !class_exists($driverConfig['class'])) {
            throw new RuntimeException("FileManager: Invalid driver [$active]");
        }

        return app()->make($driverConfig['class'], ['config' => $driverConfig]);
    }
}