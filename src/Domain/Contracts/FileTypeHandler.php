<?php

namespace M2code\FileManager\Domain\Contracts;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\DTO\FileOperationResult;

interface FileTypeHandler
{
    public function canHandle(FileInput $input): bool;
    public function handleSave(FileInput $input, string $folder): FileOperationResult;
}
