<?php

namespace M2code\FileManager\DTO;

use M2code\FileManager\Domain\ValueObjects\FileVariants;

readonly class ImageUploadResult
{
    public function __construct(
        public FileVariants $variants,
        public ?string $blurhash = null
    ) {}

    public function __get(string $name): ?string
    {
        return match ($name) {
            'path' => $this->variantPath('original'),
            'lowQualityPath' => $this->variantPath('low_quality'),
            'watermarkPath' => $this->variantPath('watermark'),
            'optimizedPath' => $this->variantPath('optimized'),
            default => null,
        };
    }

    protected function variantPath(string $type): ?string
    {
        return $this->variants->get($type)?->path;
    }
}
