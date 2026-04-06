<?php

namespace M2code\FileManager\Application\FileRouter;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;

class SvgFileHandler implements FileTypeHandler
{
    public function __construct(protected FileSaver $driver) {}

    public function canHandle(FileInput $input): bool
    {
        return strtolower($input->getMimeType()) === 'image/svg+xml';
    }

    public function handleSave(FileInput $input, string $folder): FileOperationResult
    {
        return $this->driver->save($input, $folder);
    }
}
