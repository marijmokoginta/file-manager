<?php

namespace M2code\FileManager\Application\Uploader;

use M2code\FileManager\Application\Image\ImageProcessor;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\ValueObjects\FileVariant;
use M2code\FileManager\Domain\ValueObjects\FileVariants;
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

    public function blur(): self { $this->blur = true; return $this; }
    public function watermark(): self { $this->watermark = true; return $this; }
    public function lowQuality(): self { $this->lowQuality = true; return $this; }

    public function enableBlur(): self { return $this->blur(); }
    public function enableWatermark(): self { return $this->watermark(); }
    public function enableLowQuality(): self { return $this->lowQuality(); }

    public function upload($file, string $folder): ImageUploadResult
    {
        $processedVariants = $this->processor
            ->reset()
            ->withBlur($this->blur)
            ->withWatermark($this->watermark)
            ->withLowQuality($this->lowQuality)
            ->process($file);

        $original = $this->driver->save($file, $folder);
        $variants = new FileVariants();
        $variants->add(new FileVariant('original', $original->filePath));

        foreach ($processedVariants->all() as $processedVariant) {
            $saved = $this->driver->save($processedVariant->path, $folder);
            $variants->add(new FileVariant($processedVariant->type, $saved->filePath));
        }

        return new ImageUploadResult(
            variants: $variants,
            blurhash: $this->processor->getBlurhash()
        );
    }
}
