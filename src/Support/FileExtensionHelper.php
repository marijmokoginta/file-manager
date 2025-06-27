<?php

namespace M2code\FileManager\Support;

use Illuminate\Http\UploadedFile;

class FileExtensionHelper
{
    public static function resolve($file): string
    {
        if ($file instanceof UploadedFile) {
            return $file->getClientOriginalExtension();
        }

        return pathinfo($file, PATHINFO_EXTENSION) ?? 'bin';
    }
}