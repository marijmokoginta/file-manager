<?php

namespace M2code\FileManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string move(string $tmpPath, string $destinationFolder, ?string $disk = null)
 * @method static array  moveAll(string $tmpFolder, string $destinationFolder, ?string $disk = null)
 */
class FileMover extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-mover';
    }
}
