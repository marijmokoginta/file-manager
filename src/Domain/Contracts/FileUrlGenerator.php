<?php

namespace M2code\FileManager\Domain\Contracts;

use DateTimeInterface;

interface FileUrlGenerator
{
    public function getUrl(string $path): string;

    public function getSignedUrl(string $path, DateTimeInterface $expiresAt): string;

    /**
     * Decode an encoded path back to the original file path.
     * Supports both the new encrypted format and legacy base64 encoding.
     *
     * @param  string  $encoded  The encoded path from the URL
     * @return string|null  The decoded file path, or null if decoding fails
     */
    public function decodePath(string $encoded): ?string;
}
