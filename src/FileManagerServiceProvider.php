<?php

namespace M2code\FileManager;

use Illuminate\Support\ServiceProvider;
use M2code\FileManager\Application\FileRouter\FileTypeRouterService;
use M2code\FileManager\Application\FileRouter\ImageFileHandler;
use M2code\FileManager\Console\TestUploadCommand;
use M2code\FileManager\Core\FileDriverResolver;
use M2code\FileManager\Core\FileUrlGeneratorResolver;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;

class FileManagerServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/file-manager.php', 'file-manager');

        $this->app->bind(FileSaver::class, function () {
            $driver = FileDriverResolver::resolve();

            return new FileTypeRouterService([
                new ImageFileHandler($driver),

                // Other handlers
            ]);
        });

        $this->app->singleton('file-manager', function () {
            return app(FileSaver::class);
        });

        $this->app->singleton(FileUrlGenerator::class, function () {
            return FileUrlGeneratorResolver::resolve();
        });

        $this->app->singleton('file-url', function () {
            return app(FileUrlGenerator::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/file-manager.php' => config_path('file-manager.php')
        ], 'config');

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestUploadCommand::class
            ]);
        }
    }

}