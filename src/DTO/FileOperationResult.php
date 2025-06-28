<?php

namespace M2code\FileManager\DTO;

readonly class FileOperationResult
{
    public function __construct(
        public string $filePath,
        public ?string $fileName = null,
    ) {}
}