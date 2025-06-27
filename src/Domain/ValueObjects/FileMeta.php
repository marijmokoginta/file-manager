<?php

namespace M2code\FileManager\Domain\ValueObjects;

class FileMeta
{
    public $name;
    public $mime;
    public $extension;
    public $size;

    public function __construct(
        string $name,
        string $mime,
        string $extension,
        int $size
    ) {
        $this->size = $size;
        $this->extension = $extension;
        $this->mime = $mime;
        $this->name = $name;
    }
}