<?php

namespace M2code\FileManager\Domain\Contracts;

use M2code\FileManager\DTO\FileOperationResult;

interface FileSaver
{
    /**
     * Save a file to storage.
     *
     * @param  mixed  $file  FileInput, UploadedFile, base64 data URI, or raw content
     * @param  string  $folder  Destination folder path
     * @param  string|null  $fileName  Optional filename (auto-generated if null)
     * @param  bool|null  $encrypted  Override encryption setting (null = use config)
     * @return FileOperationResult
     */
    public function save($file, string $folder, ?string $fileName = null, ?bool $encrypted = null): FileOperationResult;
}
