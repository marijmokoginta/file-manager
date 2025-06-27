<?php

namespace M2code\FileManager\Domain\Contracts;

use M2code\FileManager\DTO\FileOperationResult;

interface FileTypeHandler
{
    public function canHandle($file): bool;
    public function handleSave($file, string $folder): FileOperationResult;
}