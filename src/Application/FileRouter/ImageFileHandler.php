<?php

namespace M2code\FileManager\Application\FileRouter;

use Illuminate\Http\UploadedFile;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;

class ImageFileHandler implements FileTypeHandler
{

    public function __construct(protected FileSaver $driver) {}

    public function canHandle($file): bool
    {
        return $file instanceof UploadedFile
            ? str_starts_with($file->getMimeType(), 'image/')
            : (is_string($file) && str_starts_with($file, 'data:image/'));
    }

    public function handleSave($file, string $folder): FileOperationResult
    {
        return $this->driver->save($file, $folder);
    }
}