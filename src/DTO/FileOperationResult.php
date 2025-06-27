<?php

namespace M2code\FileManager\DTO;

class FileOperationResult
{
    public $filePath;
    public $fileName = null;
    public $thumbPath = null;
    public $blurhash = null;

    public function __construct(
        string $filePath,
        string $fileName = null,
        string $thumbPath = null,
        string $blurhash = null
    )
    {
        $this->blurhash = $blurhash;
        $this->thumbPath = $thumbPath;
        $this->fileName = $fileName;
        $this->filePath = $filePath;

    }
}