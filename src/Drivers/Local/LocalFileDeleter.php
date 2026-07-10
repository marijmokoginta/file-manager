<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Domain\Contracts\FileDeleter;

class LocalFileDeleter implements FileDeleter
{
    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 'public';
    }

    public function delete(string $path): bool
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return true;
        }

        $storage = Storage::disk($this->disk);
        if (! $storage->exists($path)) {
            return true;
        }

        return (bool) $storage->delete($path);
    }

    public function deleteMany(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $key = (string) $path;
            $results[$key] = $this->delete($key);
        }

        return $results;
    }
}
