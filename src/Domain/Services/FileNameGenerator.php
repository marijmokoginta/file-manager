<?php

namespace M2code\FileManager\Domain\Services;

use Illuminate\Support\Str;

class FileNameGenerator
{
    public static function generate(string $ext = 'bin'): string
    {
        return Str::random(32) . '.' . $ext;
    }
}