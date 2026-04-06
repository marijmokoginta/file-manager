<?php

namespace M2code\FileManager\Facades;

use Illuminate\Support\Facades\Facade;
use M2code\FileManager\Domain\ValueObjects\FileVariants;
use M2code\FileManager\DTO\FileOperationResult;

/**
 * @method static FileOperationResult save($file, string $folder)
 * @method static bool delete(string $path)
 * @method static array deleteMany(array $paths)
 * @method static array deleteVariants(FileVariants $variants)
 */
class FileManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-manager';
    }
}
