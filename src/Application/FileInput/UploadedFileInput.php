<?php

namespace M2code\FileManager\Application\FileInput;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class UploadedFileInput implements FileInput
{
    public function __construct(protected UploadedFile $file) {}

    public function getContent(): string
    {
        $realPath = $this->file->getRealPath();
        if ($realPath === false) {
            throw new RuntimeException('UploadedFile has no readable real path.');
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new RuntimeException("Unable to read uploaded file from [$realPath].");
        }

        return $content;
    }

    public function getMimeType(): string
    {
        return strtolower((string) ($this->file->getMimeType() ?: 'application/octet-stream'));
    }

    public function getExtension(): string
    {
        return strtolower($this->file->getClientOriginalExtension() ?: 'bin');
    }
}
