<?php

namespace M2code\FileManager;

use Illuminate\Support\ServiceProvider;

class FileManagerServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/file-manger.php', 'file-manager');

        $this->app->singleton('file-manager', function () {
            return \M2code\FileManager\Core\FileDriverResolver::resolve();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/file-manager.php' => config_path('file-manager.php')
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \M2code\FileManager\Console\TestUploadCommand::class
            ]);
        }
    }

}