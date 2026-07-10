<?php

namespace M2code\FileManager\Domain\Contracts;

use DateTimeInterface;

interface FileUrlGenerator
{
    public function getUrl(string $path): string;

    public function getSignedUrl(string $path, DateTimeInterface $expiresAt): string;
}
