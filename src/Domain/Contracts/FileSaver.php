<?php

namespace M2code\FileManager\Domain\Contracts;

use M2code\FileManager\DTO\FileOperationResult;

interface FileSaver
{
    public function save($file, string $folder): FileOperationResult;
}