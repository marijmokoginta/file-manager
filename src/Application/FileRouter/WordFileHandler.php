<?php

namespace M2code\FileManager\Application\FileRouter;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\DTO\UploadResponse;

/**
 * OOXML Word document (.docx) handler — stored as-is (no parsing, no
 * variants): a valid .docx is a ZIP container whose [Content_Types].xml
 * declares the wordprocessingml document part. Macro-enabled containers
 * (.docm/.dotm) declare the macroEnabled override (or ship vbaProject.bin)
 * and are rejected here (defense-in-depth under whatever the host
 * application whitelists — finfo alone cannot tell them apart).
 */
class WordFileHandler implements FileTypeHandler
{
    /**
     * MIME jenis utama Word OOXML (wordprocessingml.document).
     */
    public const string MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function __construct(protected FileSaver $driver) {}

    public function canHandle(FileInput $input): bool
    {
        return strtolower($input->getMimeType()) === self::MIME_DOCX;
    }

    public function handleSave(FileInput $input, string $folder, ?string $fileName = null, ?bool $encrypted = null): FileOperationResult
    {
        return $this->driver->save($input, $folder, $fileName, $encrypted);
    }

    public function handleUpload(FileInput $input, string $folder, array $options): UploadResponse
    {
        $result = $this->driver->save($input, $folder);

        return new UploadResponse(
            type: 'document',
            tmpPath: $result->filePath,
            tmpFolder: $folder,
            originalName: $input->getClientOriginalName(),
            size: strlen($input->getContent()),
            mimeType: $input->getMimeType(),
        );
    }

    public function supportedOptions(): array
    {
        return [];
    }

    public function defaultOptions(): array
    {
        return [];
    }
}
