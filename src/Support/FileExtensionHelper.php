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

        if (is_string($file) && str_starts_with($file, 'data:')) {
            $mime = self::extractMimeType($file);
            return self::mapMimeToExtension($mime);
        }

        if (is_string($file)) {
            $ext = pathinfo(parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION);
            return $ext ?: 'bin';
        }

        return 'bin';
    }

    protected static function extractMimeType(string $dataUri): ?string
    {
        if (preg_match('#^data:(.*?);base64,#', $dataUri, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected static function mapMimeToExtension(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'bin',
        };
    }
}