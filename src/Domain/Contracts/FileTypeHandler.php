<?php

namespace M2code\FileManager\Domain\Contracts;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\DTO\UploadResponse;

interface FileTypeHandler
{
    public function canHandle(FileInput $input): bool;

    /**
     * Save a file to permanent storage.
     *
     * @param  FileInput  $input  Normalized file input
     * @param  string  $folder  Destination folder
     * @param  string|null  $fileName  Optional filename (auto-generated if null)
     * @param  bool|null  $encrypted  Override encryption setting (null = use config)
     * @return FileOperationResult
     */
    public function handleSave(FileInput $input, string $folder, ?string $fileName = null, ?bool $encrypted = null): FileOperationResult;

    /**
     * Handle file upload for the API, with flexible options.
     */
    public function handleUpload(FileInput $input, string $folder, array $options): UploadResponse;

    /**
     * List of supported option keys for this file type.
     */
    public function supportedOptions(): array;

    /**
     * Default option values for this file type.
     */
    public function defaultOptions(): array;
}
