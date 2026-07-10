<?php

namespace M2code\FileManager\Domain\Contracts;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\DTO\UploadResponse;

interface FileTypeHandler
{
    public function canHandle(FileInput $input): bool;

    public function handleSave(FileInput $input, string $folder): FileOperationResult;

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
