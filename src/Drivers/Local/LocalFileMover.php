<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\FileInput\RawContentFileInput;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use M2code\FileManager\Domain\Contracts\FileMover;
use M2code\FileManager\Domain\Contracts\FileSaver;
use RuntimeException;

class LocalFileMover implements FileMover
{
    protected string $tmpDisk;

    protected string $defaultDisk;

    protected ?FileSaver $saver;

    protected ?ContentEncryptor $encryptor;

    public function __construct(
        array $config = [],
        ?FileSaver $saver = null,
        ?ContentEncryptor $encryptor = null,
    ) {
        $this->tmpDisk = Config::get('file-manager.tmp.disk', 'local');
        $this->defaultDisk = $config['disk'] ?? Config::get('file-manager.drivers.local.disk', 'public');
        $this->saver = $saver;
        $this->encryptor = $encryptor;
    }

    public function move(string $tmpPath, string $destinationFolder, ?string $disk = null): string
    {
        $targetDisk = $disk ?? $this->defaultDisk;

        $tmpStorage = Storage::disk($this->tmpDisk);

        if (! $tmpStorage->exists($tmpPath)) {
            throw new RuntimeException("Source file not found in tmp storage: {$tmpPath}");
        }

        $fileName = basename($tmpPath);
        $contents = $tmpStorage->get($tmpPath);

        if ($this->saver) {
            // Move via FileSaver — encryption handled by saver
            $input = new RawContentFileInput(
                content: $contents,
                extension: pathinfo($fileName, PATHINFO_EXTENSION),
                clientOriginalName: $fileName,
            );

            $result = $this->saver->save($input, $destinationFolder, $fileName);

            // Delete from tmp after successful save
            $tmpStorage->delete($tmpPath);

            return $result->filePath;
        }

        // Fallback: direct Storage::put() with optional manual encryption
        $targetStorage = Storage::disk($targetDisk);

        $destinationPath = trim($destinationFolder, '/').'/'.$fileName;

        if ($this->encryptor) {
            $contents = $this->encryptor->encrypt($contents);
        }

        $saved = $targetStorage->put($destinationPath, $contents);

        if (! $saved) {
            throw new RuntimeException("Failed to move file to: {$destinationPath}");
        }

        if (! $targetStorage->exists($destinationPath)) {
            throw new RuntimeException("File verification failed after move: {$destinationPath}");
        }

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
