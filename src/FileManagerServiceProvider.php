<?php

namespace M2code\FileManager;

use Illuminate\Support\ServiceProvider;
use M2code\FileManager\Application\FileManagerService;
use M2code\FileManager\Application\FileRouter\FileTypeRouterService;
use M2code\FileManager\Application\FileRouter\ImageFileHandler;
use M2code\FileManager\Application\FileRouter\PdfFileHandler;
use M2code\FileManager\Application\FileRouter\SvgFileHandler;
use M2code\FileManager\Application\Image\Actions\ApplyWatermarkAction;
use M2code\FileManager\Application\Image\Actions\GenerateBlurAction;
use M2code\FileManager\Application\Image\Actions\GenerateLowQualityAction;
use M2code\FileManager\Application\Image\Actions\GenerateOptimizedImageAction;
use M2code\FileManager\Application\Image\ImageProcessor;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Application\UploadService;
use M2code\FileManager\Console\TestUploadCommand;
use M2code\FileManager\Core\FileDeleterResolver;
use M2code\FileManager\Core\FileDriverResolver;
use M2code\FileManager\Core\FileUrlGeneratorResolver;
use M2code\FileManager\Domain\Contracts\FileDeleter;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;
use Illuminate\Routing\Router;

class FileManagerServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/file-manager.php', 'file-manager');

        $this->app->bind(FileSaver::class, function () {
            $driver = FileDriverResolver::resolve();

            return new FileTypeRouterService([
                new SvgFileHandler($driver),
                new PdfFileHandler($driver),
                new ImageFileHandler($driver),

                // Other handlers
            ]);
        });

        $this->app->bind(FileDeleter::class, function () {
            return FileDeleterResolver::resolve();
        });

        $this->app->bind(GenerateBlurAction::class, fn () => new GenerateBlurAction());
        $this->app->bind(GenerateLowQualityAction::class, fn () => new GenerateLowQualityAction());
        $this->app->bind(ApplyWatermarkAction::class, fn () => new ApplyWatermarkAction());
        $this->app->bind(GenerateOptimizedImageAction::class, fn () => new GenerateOptimizedImageAction());

        $this->app->bind(ImageProcessor::class, function ($app) {
            return new ImageProcessor(
                $app->make(GenerateBlurAction::class),
                $app->make(GenerateLowQualityAction::class),
                $app->make(ApplyWatermarkAction::class),
                $app->make(GenerateOptimizedImageAction::class)
            );
        });

        $this->app->bind(ImageUploader::class, function ($app) {
            $driver = FileDriverResolver::resolve();

            return new ImageUploader(
                $driver,
                $app->make(ImageProcessor::class)
            );
        });

        $this->app->bind(FileManagerService::class, function ($app) {
            return new FileManagerService(
                $app->make(FileSaver::class),
                $app->make(FileDeleter::class)
            );
        });

        $this->app->singleton('file-manager', function () {
            return app(FileManagerService::class);
        });

        $this->app->singleton(FileUrlGenerator::class, function () {
            return FileUrlGeneratorResolver::resolve();
        });

        $this->app->singleton('file-url', function () {
            return app(FileUrlGenerator::class);
        });

        $this->app->singleton(UploadService::class, fn () => new UploadService());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/file-manager.php' => config_path('file-manager.php')
        ], 'config');

        $this->registerMiddleware();

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestUploadCommand::class
            ]);
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('file-manager.api', \M2code\FileManager\Http\Middleware\FileManagerApiAuth::class);
    }

}
