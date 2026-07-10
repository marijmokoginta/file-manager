<?php

namespace M2code\FileManager\Application\Uploader;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Application\FileInput\FileInputFactory;
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

    protected bool $optimize = false;

    protected string $optimizeFormat = 'avif';

    protected ?bool $encrypted = null;

    public function __construct(
        protected FileSaver $driver,
        protected ImageProcessor $processor
    ) {}

    public static function make(): self
    {
        return app(self::class);
    }

    public function blur(): self
    {
        $this->blur = true;

        return $this;
    }

    public function watermark(): self
    {
        $this->watermark = true;

        return $this;
    }

    public function lowQuality(): self
    {
        $this->lowQuality = true;

        return $this;
    }

    public function optimize(string $format = 'avif'): self
    {
        $this->optimize = true;
        $this->optimizeFormat = strtolower($format);

        return $this;
    }

    public function encrypted(bool $enabled = true): self
    {
        $this->encrypted = $enabled;

        return $this;
    }

    public function enableBlur(): self
    {
        return $this->blur();
    }

    public function enableWatermark(): self
    {
        return $this->watermark();
    }

    public function enableLowQuality(): self
    {
        return $this->lowQuality();
    }

    public function enableOptimize(string $format = 'avif'): self
    {
        return $this->optimize($format);
    }

    public function withOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            if (! is_bool($value)) {
                continue;
            }

            match ($key) {
                'blurhash' => $this->blur = $value,
                'watermark' => $this->watermark = $value,
                'low_quality' => $this->lowQuality = $value,
                'optimize' => $this->optimize = $value,
                'encrypted' => $this->encrypted = $value,
                default => null,
            };
        }

        return $this;
    }

    public function upload($file, string $folder, ?FileSaver $driver = null): ImageUploadResult
    {
        $saver = $driver ?? $this->driver;

        $input = FileInputFactory::from($file);
        $isSvg = $input->getMimeType() === 'image/svg+xml';

        $original = $saver->save($input, $folder, encrypted: $this->encrypted);
        $variants = new FileVariants;
        $variants->add(new FileVariant('original', $original->filePath));

        if ($isSvg) {
            return new ImageUploadResult(
                variants: $variants,
                blurhash: null
            );
        }

        $processedVariants = $this->processor
            ->reset()
            ->withBlur($this->blur)
            ->withWatermark($this->watermark)
            ->withLowQuality($this->lowQuality)
            ->withOptimize($this->optimize, $this->optimizeFormat)
            ->process($this->resolveProcessableSource($input, $file));

        foreach ($processedVariants->all() as $processedVariant) {
            $saved = $saver->save($processedVariant->path, $folder, encrypted: $this->encrypted);
            $variants->add(new FileVariant($processedVariant->type, $saved->filePath));
        }

        return new ImageUploadResult(
            variants: $variants,
            blurhash: $this->processor->getBlurhash()
        );
    }

    /**
     * Resolve a source that Intervention Image can read.
     *
     * If the original file is a FileInput object, Intervention Image can't
     * decode it directly since it doesn't implement SplFileInfo. We write
     * the content to a temp file and return the path.
     */
    protected function resolveProcessableSource(FileInput $input, mixed $file): mixed
    {
        if ($file instanceof FileInput) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'fm_img_');
            file_put_contents($tmpPath, $input->getContent());

            return $tmpPath;
        }

        return $file;
    }
}
