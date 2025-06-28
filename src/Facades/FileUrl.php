<?php

namespace M2code\FileManager\Facades;

use DateTimeInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string getUrl(string $path)
 * @method static string getSignedUrl(string $path, DateTimeInterface $expiresAt)
 */
class FileUrl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-url';
    }
}