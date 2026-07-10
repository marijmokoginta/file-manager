<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Domain\Contracts\FileMover;
use RuntimeException;

class LocalFileMover implements FileMover
{
    protected string $tmpDisk;

    protected string $defaultDisk;

    public function __construct(array $config = [])
    {
        $this->tmpDisk = Config::get('file-manager.tmp.disk', 'local');
        $this->defaultDisk = $config['disk'] ?? Config::get('file-manager.drivers.local.disk', 'public');
    }

    public function move(string $tmpPath, string $destinationFolder, ?string $disk = null): string
    {
        $targetDisk = $disk ?? $this->defaultDisk;

        $tmpStorage = Storage::disk($this->tmpDisk);
        $targetStorage = Storage::disk($targetDisk);

        if (! $tmpStorage->exists($tmpPath)) {
            throw new RuntimeException("Source file not found in tmp storage: {$tmpPath}");
        }

        $fileName = basename($tmpPath);
        $destinationPath = trim($destinationFolder, '/').'/'.$fileName;

        $contents = $tmpStorage->get($tmpPath);
        $saved = $targetStorage->put($destinationPath, $contents);

        if (! $saved) {
            throw new RuntimeException("Failed to move file to: {$destinationPath}");
        }

        // Verify the file exists on target before deleting from tmp
        if (! $targetStorage->exists($destinationPath)) {
            throw new RuntimeException("File verification failed after move: {$destinationPath}");
        }

        // Delete from tmp after successful move
        $tmpStorage->delete($tmpPath);

        return $destinationPath;
    }

    public function moveAll(string $tmpFolder, string $destinationFolder, ?string $disk = null): array
    {
        $tmpStorage = Storage::disk($this->tmpDisk);
        $targetDisk = $disk ?? $this->defaultDisk;

        $allFiles = $tmpStorage->allFiles($tmpFolder);

        $results = [];
        foreach ($allFiles as $tmpPath) {
            $results[$tmpPath] = $this->move($tmpPath, $destinationFolder, $targetDisk);
        }

        return $results;
    }
}
