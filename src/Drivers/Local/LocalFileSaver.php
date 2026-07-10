<?php

namespace M2code\FileManager\Drivers\Local;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Application\FileInput\FileInputFactory;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Services\FileNameGenerator;
use M2code\FileManager\DTO\FileOperationResult;
use RuntimeException;

class LocalFileSaver implements FileSaver
{
    protected string $disk;

    protected ContentEncryptor $encryptor;

    protected bool $encryptionEnabled;

    public function __construct(
        array $config = [],
        ?ContentEncryptor $encryptor = null,
    ) {
        $this->disk = $config['disk'] ?? 'public';
        $this->encryptor = $encryptor ?? new class implements ContentEncryptor
        {
            public function encrypt(string $data): string { return $data; }
            public function decrypt(string $data): string { return $data; }
        };
        $this->encryptionEnabled = Config::get('file-manager.encryption.enabled', false);
    }

    public function save($file, string $folder, ?string $fileName = null, ?bool $encrypted = null): FileOperationResult
    {
        $input = $file instanceof FileInput ? $file : FileInputFactory::from($file);

        $ext = $fileName ? pathinfo($fileName, PATHINFO_EXTENSION) : $input->getExtension();
        $fileName = $fileName ?? FileNameGenerator::generate($ext);
        $path = trim($folder, '/').'/'.$fileName;
        $contents = $input->getContent();

        $shouldEncrypt = $encrypted ?? $this->encryptionEnabled;
        if ($shouldEncrypt) {
            $contents = $this->encryptor->encrypt($contents);
        }

        $saved = Storage::disk($this->disk)->put($path, $contents);
        if ($saved === false) {
            throw new RuntimeException("Unable to save file at path [$path] on disk [{$this->disk}]");
        }

        return new FileOperationResult($path, $fileName);
    }
}
