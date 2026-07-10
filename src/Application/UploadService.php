<?php

namespace M2code\FileManager\Application;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Application\FileInput\FileInputFactory;
use M2code\FileManager\Application\FileRouter\ImageFileHandler;
use M2code\FileManager\Application\FileRouter\PdfFileHandler;
use M2code\FileManager\Application\FileRouter\SvgFileHandler;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\DTO\UploadResponse;
use RuntimeException;
use Throwable;

class UploadService
{
    public function upload(mixed $file, array $options = [], ?string $cancelToken = null): UploadResponse
    {
        $this->assertNotCancelled($cancelToken);

        $input = FileInputFactory::from($file);

        $this->validateSize($input);

        $this->assertNotCancelled($cancelToken);

        $handler = $this->resolveHandler($input);
        $folder = $this->generateTmpFolder();

        $this->assertNotCancelled($cancelToken);

        return $this->retry(fn () => $handler->handleUpload($input, $folder, $options));
    }

    /**
     * Mark a cancel token as cancelled. Subsequent uploads with this token
     * will throw UploadCancelledException.
     */
    public function cancel(string $token): void
    {
        Cache::put(
            $this->cancelCacheKey($token),
            true,
            now()->addMinutes(10),
        );
    }

    /**
     * Check if the cancel token has been marked as cancelled.
     */
    public function isCancelled(?string $token): bool
    {
        if (! $token) {
            return false;
        }

        return Cache::has($this->cancelCacheKey($token));
    }

    protected function assertNotCancelled(?string $token): void
    {
        if ($this->isCancelled($token)) {
            throw new UploadCancelledException((string) $token);
        }
    }

    protected function cancelCacheKey(string $token): string
    {
        return 'file-manager:cancel:'.$token;
    }

    protected function validateSize(FileInput $input): void
    {
        $category = $this->resolveCategory($input->getMimeType());
        $maxSizes = Config::get('file-manager.validation.max_file_size', []);
        $maxKiB = $maxSizes[$category] ?? $maxSizes['default'] ?? 10240;

        $sizeKiB = strlen($input->getContent()) / 1024;

        if ($sizeKiB > $maxKiB) {
            throw new RuntimeException(
                sprintf(
                    'File size (%s) exceeds the maximum allowed size of %d KiB for %s files.',
                    $this->formatSize($sizeKiB),
                    $maxKiB,
                    $category,
                )
            );
        }
    }

    protected function resolveHandler(FileInput $input): FileTypeHandler
    {
        $tmpDriver = $this->resolveTmpDriver();

        $handlers = [
            new SvgFileHandler($tmpDriver),
            new PdfFileHandler($tmpDriver),
            new ImageFileHandler($tmpDriver, app(ImageUploader::class)),
        ];

        foreach ($handlers as $handler) {
            if ($handler->canHandle($input)) {
                return $handler;
            }
        }

        throw new RuntimeException('Unsupported file type: '.$input->getMimeType());
    }

    protected function resolveTmpDriver(): FileSaver
    {
        $disk = Config::get('file-manager.tmp.disk', 'local');

        return new LocalFileSaver(['disk' => $disk]);
    }

    protected function generateTmpFolder(): string
    {
        $prefix = Config::get('file-manager.tmp.prefix', 'tmp/uploads');

        return trim($prefix, '/').'/'.(string) Str::uuid();
    }

    protected function resolveCategory(string $mimeType): string
    {
        $mimeType = strtolower($mimeType);

        if ($mimeType === 'image/svg+xml') {
            return 'image';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType === 'application/pdf') {
            return 'document';
        }

        return 'default';
    }

    protected function retry(callable $callback): UploadResponse
    {
        $enabled = Config::get('file-manager.upload.retry.enabled', true);

        if (! $enabled) {
            return $callback();
        }

        $maxAttempts = (int) Config::get('file-manager.upload.retry.max_attempts', 3);
        $delay = (int) Config::get('file-manager.upload.retry.delay', 100);

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempt === $maxAttempts) {
                    break;
                }

                usleep($delay * 1000);
            }
        }

        throw $lastException;
    }

    protected function formatSize(float $sizeKiB): string
    {
        if ($sizeKiB >= 1024) {
            return round($sizeKiB / 1024, 2).' MiB';
        }

        return round($sizeKiB, 2).' KiB';
    }
}
