<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Application\FileInput\FileInputFactory;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Services\FileNameGenerator;
use M2code\FileManager\DTO\FileOperationResult;
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
        $input = $file instanceof FileInput ? $file : FileInputFactory::from($file);

        $ext = $input->getExtension();
        $fileName = FileNameGenerator::generate($ext);
        $path = trim($folder, '/') . '/' . $fileName;
        $contents = $input->getContent();

        $saved = Storage::disk($this->disk)->put($path, $contents);
        if ($saved === false) {
            throw new RuntimeException("Unable to save file at path [$path] on disk [{$this->disk}]");
        }

        return new FileOperationResult($path, $fileName);
    }
}
