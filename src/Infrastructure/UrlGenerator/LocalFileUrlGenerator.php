<?php

namespace M2code\FileManager\Infrastructure\UrlGenerator;

use M2code\FileManager\Domain\Contracts\FileUrlGenerator;

class LocalFileUrlGenerator implements FileUrlGenerator
{
    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 'public';
    }

    public function generate(string $path): string
    {
        return 'file_url';
    }
}