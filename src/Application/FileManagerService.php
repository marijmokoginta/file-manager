<?php

namespace M2code\FileManager\Application;

use M2code\FileManager\Domain\Contracts\FileDeleter;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\ValueObjects\FileVariants;
use M2code\FileManager\DTO\FileOperationResult;

class FileManagerService
{
    public function __construct(
        protected FileSaver $saver,
        protected FileDeleter $deleter
    ) {}

    public function save($file, string $folder): FileOperationResult
    {
        return $this->saver->save($file, $folder);
    }

    public function delete(string $path): bool
    {
        return $this->deleter->delete($path);
    }

    public function deleteMany(array $paths): array
    {
        return $this->deleter->deleteMany($paths);
    }

    public function deleteVariants(FileVariants $variants): array
    {
        return $this->deleteMany($variants->paths());
    }
}
