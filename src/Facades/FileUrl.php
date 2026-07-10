<?php

namespace M2code\FileManager\Facades;

use DateTimeInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string getUrl(string $path)
 * @method static string getSignedUrl(string $path, DateTimeInterface $expiresAt)
 * @method static string|null decodePath(string $encoded)
 */
class FileUrl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-url';
    }
}
