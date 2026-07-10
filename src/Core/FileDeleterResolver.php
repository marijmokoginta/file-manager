<?php

namespace M2code\FileManager\Core;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class FileDeleterResolver
{
    /**
     * @throws BindingResolutionException
     */
    public static function resolve(): object
    {
        $deleters = Config::get('file-manager.deleters', []);
        $active = Config::get(
            'file-manager.default_deleter',
            Config::get('file-manager.default_driver', 'local')
        );

        $deleterConfig = $deleters[$active] ?? null;

        if (! $deleterConfig || ! class_exists($deleterConfig['class'])) {
            throw new RuntimeException("FileManager: Invalid deleter [$active]");
        }

        return app()->make($deleterConfig['class'], ['config' => $deleterConfig]);
    }
}
