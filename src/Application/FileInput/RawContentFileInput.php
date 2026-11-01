<?php

namespace M2code\FileManager\Application\FileInput;

class RawContentFileInput implements FileInput
{
    private string $content;

    private string $mimeType;

    private string $extension;

    private ?string $clientOriginalName;

    public function __construct(
        string $content,
        string $extension,
        ?string $mimeType = null,
        ?string $clientOriginalName = null
    ) {
        $this->content = $content;
        $this->extension = ltrim($extension, '.');
        $this->mimeType = $mimeType ?? $this->guessMimeType($this->extension);
        $this->clientOriginalName = $clientOriginalName;
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
        return $this->extension;
    }

    public function getClientOriginalName(): ?string
    {
        return $this->clientOriginalName;
    }

    private function guessMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg', 'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }
}
