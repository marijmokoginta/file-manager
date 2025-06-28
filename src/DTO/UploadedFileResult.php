<?php

namespace M2code\FileManager\DTO;

readonly class UploadedFileResult
{
    public function __construct(
        public string $url,
        public ?string $lowQualityUrl = null,
        public ?string $blurhash = null
    ) {}
}