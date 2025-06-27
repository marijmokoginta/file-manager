<?php

namespace M2code\FileManager\Facades;

use M2code\FileManager\DTO\FileOperationResult;

/**
 * @method static FileOperationResult save(mixed $file, string $folder)
 */
class FileManager
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-manager';
    }
}