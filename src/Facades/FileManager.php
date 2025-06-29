<?php

namespace M2code\FileManager\Facades;

use Illuminate\Support\Facades\Facade;
use M2code\FileManager\DTO\FileOperationResult;

/**
 * @method static FileOperationResult save($file, string $folder)
 */
class FileManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-manager';
    }
}