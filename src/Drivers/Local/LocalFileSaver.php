<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Services\FileNameGenerator;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\Support\FileExtensionHelper;

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
        $path = $folder . '/' . $fileName;

        Storage::disk($this->disk)->put($path, file_get_contents($file));

        return new FileOperationResult($path, $fileName);
    }
}