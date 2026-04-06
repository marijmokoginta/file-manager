<?php

namespace M2code\FileManager\Application\FileInput;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class FileInputFactory
{
    public static function from(mixed $input): FileInput
    {
        if ($input instanceof FileInput) {
            return $input;
        }

        if ($input instanceof UploadedFile) {
            return new UploadedFileInput($input);
        }

        if (is_string($input) && self::isBase64DataUri($input)) {
            return new Base64FileInput($input);
        }

        if (is_string($input) && self::isSvgString($input)) {
            return new SvgStringFileInput($input);
        }

        throw new RuntimeException('Unsupported file input. Use UploadedFile, base64 data URI, or SVG string.');
    }

    protected static function isBase64DataUri(string $value): bool
    {
        return str_starts_with($value, 'data:');
    }

    protected static function isSvgString(string $value): bool
    {
        return stripos($value, '<svg') !== false;
    }
}
