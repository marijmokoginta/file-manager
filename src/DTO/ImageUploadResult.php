<?php

namespace M2code\FileManager\DTO;

readonly class ImageUploadResult
{
    public function __construct(
        public string  $path,
        public ?string $lowQualityPath = null,
        public ?string $blurhash = null
    ) {}
}