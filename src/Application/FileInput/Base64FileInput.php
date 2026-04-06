<?php

namespace M2code\FileManager\Application\FileInput;

use RuntimeException;

class Base64FileInput implements FileInput
{
    protected string $mimeType;
    protected string $content;

    public function __construct(protected string $dataUri)
    {
        if (!preg_match('#^data:(.*?);base64,(.*)$#s', $dataUri, $matches)) {
            throw new RuntimeException('Invalid base64 data URI format.');
        }

        $this->mimeType = strtolower(trim($matches[1]));

        $decoded = base64_decode($matches[2], true);
        if ($decoded === false) {
            throw new RuntimeException('Failed to decode base64 data URI.');
        }

        $this->content = $decoded;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getExtension(): string
    {
        return $this->mapMimeToExtension($this->mimeType);
    }

    protected function mapMimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            default => 'bin',
        };
    }
}
