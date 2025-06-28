<?php

namespace M2code\FileManager\Core;

use http\Exception\RuntimeException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Config;

class FileUrlGeneratorResolver
{
    /**
     * @throws BindingResolutionException
     */
    public static function resolve(): object
    {
        $generators = Config::get('file-manager.url_generators', []);
        $active = Config::get('file-manager.default_url_generator', 'local');

        $generatorConfig = $generators[$active] ?? null;

        if (!$generatorConfig || !class_exists($generatorConfig['class'])) {
            throw new RuntimeException("FileManager: Invalid URL generator [$active]");
        }

        return app()->make($generatorConfig['class'], ['config' => $generatorConfig]);
    }
}