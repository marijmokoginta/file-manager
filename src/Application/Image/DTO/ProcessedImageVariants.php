<?php

namespace M2code\FileManager\Application\Image\DTO;

readonly class ProcessedImageVariants
{
    public function __construct(
        public ?string $blurhash,
        public ?string $low,
        public ?string $watermark,
    ) {}
}