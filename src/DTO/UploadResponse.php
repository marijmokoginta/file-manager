<?php

namespace M2code\FileManager\DTO;

readonly class UploadResponse implements \JsonSerializable
{
    public function __construct(
        public string $type,
        public string $tmpPath,
        public string $tmpFolder,
        public ?string $originalName,
        public int $size,
        public string $mimeType,
        public array $extra = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'tmp_path' => $this->tmpPath,
            'tmp_folder' => $this->tmpFolder,
            'original_name' => $this->originalName,
            'size' => $this->size,
            'mime_type' => $this->mimeType,
            'extra' => (object) $this->extra,
        ];
    }
}
