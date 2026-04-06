<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Services\FileNameGenerator;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\Support\FileExtensionHelper;
use RuntimeException;

class LocalFileSaver implements FileSaver
{
    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 'public';
    }

    public function save($file, string $folder): FileOperationResult
    {
        $ext = FileExtensionHelper::resolve($file);
        $fileName = FileNameGenerator::generate($ext);
        $path = trim($folder, '/') . '/' . $fileName;
        $contents = $this->extractContents($file);

        $saved = Storage::disk($this->disk)->put($path, $contents);
        if ($saved === false) {
            throw new RuntimeException("Unable to save file at path [$path] on disk [{$this->disk}]");
        }

        return new FileOperationResult($path, $fileName);
    }

    protected function extractContents($file): string
    {
        if ($file instanceof UploadedFile) {
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                throw new RuntimeException('UploadedFile has no readable real path.');
            }

            $contents = file_get_contents($realPath);
            if ($contents === false) {
                throw new RuntimeException("Unable to read uploaded file from [$realPath].");
            }

            return $contents;
        }

        if (is_string($file) && str_starts_with($file, 'data:')) {
            return $this->decodeDataUri($file);
        }

        if (is_string($file) && is_file($file)) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException("Unable to read file from [$file].");
            }

            return $contents;
        }

        if (is_string($file)) {
            return $file;
        }

        throw new RuntimeException('Unsupported file input type.');
    }

    protected function decodeDataUri(string $dataUri): string
    {
        if (!preg_match('#^data:.*?;base64,(.*)$#', $dataUri, $matches)) {
            throw new RuntimeException('Invalid base64 data URI format.');
        }

        $decoded = base64_decode($matches[1], true);
        if ($decoded === false) {
            throw new RuntimeException('Failed to decode base64 data URI.');
        }

        return $decoded;
    }
}
