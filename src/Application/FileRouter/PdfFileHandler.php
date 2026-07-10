<?php

namespace M2code\FileManager\Application\FileRouter;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\DTO\UploadResponse;

class PdfFileHandler implements FileTypeHandler
{
    public function __construct(protected FileSaver $driver) {}

    public function canHandle(FileInput $input): bool
    {
        return strtolower($input->getMimeType()) === 'application/pdf';
    }

    public function handleSave(FileInput $input, string $folder): FileOperationResult
    {
        return $this->driver->save($input, $folder);
    }

    public function handleUpload(FileInput $input, string $folder, array $options): UploadResponse
    {
        $result = $this->driver->save($input, $folder);

        return new UploadResponse(
            type: 'document',
            tmpPath: $result->filePath,
            tmpFolder: $folder,
            originalName: $input->getClientOriginalName(),
            size: strlen($input->getContent()),
            mimeType: $input->getMimeType(),
        );
    }

    public function supportedOptions(): array
    {
        return [];
    }

    public function defaultOptions(): array
    {
        return [];
    }
}
