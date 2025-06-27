<?php

namespace M2code\FileManager;

use Illuminate\Support\ServiceProvider;
use M2code\FileManager\Console\TestUploadCommand;
use M2code\FileManager\Core\FileDriverResolver;
use M2code\FileManager\Domain\Contracts\FileSaver;

class FileManagerServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/file-manager.php', 'file-manager');

        $this->app->bind(FileSaver::class, function () {
            return FileDriverResolver::resolve();
        });

        $this->app->singleton('file-manager', function () {
            return FileDriverResolver::resolve();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/file-manager.php' => config_path('file-manager.php')
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestUploadCommand::class
            ]);
        }
    }

}