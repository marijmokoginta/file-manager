<?php

namespace M2code\FileManager\Application\Uploader;

use M2code\FileManager\Application\Image\ImageProcessor;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\DTO\ImageUploadResult;

class ImageUploader
{
    protected bool $blur = false;
    protected bool $watermark = false;
    protected bool $lowQuality = false;

    public function __construct(
        protected FileSaver $driver,
        protected ImageProcessor $processor
    ) {}

    public static function make(): self
    {
        return app(self::class);
    }

    public function enableBlur(): self { $this->blur = true; return $this; }
    public function enableWatermark(): self { $this->watermark = true; return $this; }
    public function enableLowQuality(): self { $this->lowQuality = true; return $this; }

    public function upload($file, string $folder): ImageUploadResult
    {
        $variants = $this->processor
            ->reset()
            ->withBlur($this->blur)
            ->withWatermark($this->watermark)
            ->withLowQuality($this->lowQuality)
            ->process($file, $folder);

        $original = $this->driver->save($file, $folder);

        $lowQualityPath = null;
        if ($variants->low) {
            $originalLow = $this->driver->save($variants->low, $folder);
            $lowQualityPath = $originalLow->filePath;
        }

        return new ImageUploadResult(
            path: $original->filePath,
            lowQualityPath: $lowQualityPath,
            blurhash: $variants->blurhash ?? null
        );
    }
}