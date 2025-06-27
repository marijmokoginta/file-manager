<?php

namespace M2code\FileManager\Application\FileRouter;

use Illuminate\Http\UploadedFile;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;

class ImageFileHandler implements FileTypeHandler
{
    protected $driver;

    public function __construct(FileSaver $driver)
    {
        $this->driver = $driver;
    }

    public function canHandle($file): bool
    {
        $mime = $file instanceof UploadedFile
            ? $file->getMimeType()
            : (is_string($file) && str_starts_with($file, 'data:image/'));

        return $mime && str_starts_with($mime, 'image/');
    }

    public function handleSave($file, string $folder): FileOperationResult
    {
        return $this->driver->save($file, $folder);
    }
}