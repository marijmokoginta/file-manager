<?php

namespace M2code\FileManager;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
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
use M2code\FileManager\Console\CleanTmpUploadsCommand;
use M2code\FileManager\Console\TestUploadCommand;
use M2code\FileManager\Core\FileDeleterResolver;
use M2code\FileManager\Core\FileDriverResolver;
use M2code\FileManager\Core\FileUrlGeneratorResolver;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use M2code\FileManager\Domain\Contracts\FileDeleter;
use M2code\FileManager\Domain\Contracts\FileMover;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;
use M2code\FileManager\Drivers\Local\LocalFileMover;
use M2code\FileManager\Http\Middleware\FileManagerApiAuth;
use M2code\FileManager\Infrastructure\Encryption\LaravelCryptEncryptor;
use M2code\FileManager\Infrastructure\Encryption\NoopEncryptor;
use M2code\FileManager\Infrastructure\Encryption\OpenSslEncryptor;

class FileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/file-manager.php', 'file-manager');

        // Bind ContentEncryptor — resolved based on config
        $this->app->singleton(ContentEncryptor::class, function () {
            if (! config('file-manager.encryption.enabled', false)) {
                return new NoopEncryptor;
            }

            $driver = config('file-manager.encryption.driver', 'laravel');

            return match ($driver) {
                'openssl' => new OpenSslEncryptor(
                    config('file-manager.encryption.key') ?? config('app.key', '')
                ),
                default => new LaravelCryptEncryptor,
            };
        });

        // Bind FileSaver — routes through FileTypeRouterService to concrete driver
        $this->app->singleton(FileSaver::class, function () {
            $driver = FileDriverResolver::resolve();

            return new FileTypeRouterService([
                new SvgFileHandler($driver),
                new PdfFileHandler($driver),
                new ImageFileHandler($driver),
            ]);
        });

        $this->app->bind(FileDeleter::class, function () {
            return FileDeleterResolver::resolve();
        });

        $this->app->bind(GenerateBlurAction::class, fn () => new GenerateBlurAction);
        $this->app->bind(GenerateLowQualityAction::class, fn () => new GenerateLowQualityAction);
        $this->app->bind(ApplyWatermarkAction::class, fn () => new ApplyWatermarkAction);
        $this->app->bind(GenerateOptimizedImageAction::class, fn () => new GenerateOptimizedImageAction);

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

        $this->app->singleton(UploadService::class, fn () => new UploadService);

        $this->app->bind(FileMover::class, function () {
            $deleterConfig = config('file-manager.deleters.local', []);

            return new LocalFileMover(
                config: $deleterConfig,
                saver: app(FileSaver::class),
                encryptor: app(ContentEncryptor::class),
            );
        });

        $this->app->singleton('file-mover', function () {
            return app(FileMover::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/file-manager.php' => config_path('file-manager.php'),
        ], 'config');

        $this->registerMiddleware();

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestUploadCommand::class,
                CleanTmpUploadsCommand::class,
            ]);

            $this->registerScheduler();
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('file-manager.api', FileManagerApiAuth::class);
    }

    protected function registerScheduler(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('file-manager:clean-tmp')->daily();
        });
    }
}
