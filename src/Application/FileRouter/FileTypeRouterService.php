<?php

namespace M2code\FileManager\Application\FileRouter;

use Illuminate\Support\Collection;
use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Application\FileInput\FileInputFactory;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\DTO\FileOperationResult;
use RuntimeException;

class FileTypeRouterService implements FileSaver
{
    protected Collection $handlers;

    public function __construct(array $handlers)
    {
        $this->handlers = collect($handlers);
    }

    public function save($file, string $folder, ?string $fileName = null, ?bool $encrypted = null): FileOperationResult
    {
        $input = $file instanceof FileInput ? $file : FileInputFactory::from($file);

        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($input)) {
                return $handler->handleSave($input, $folder, $fileName, $encrypted);
            }
        }

        throw new RuntimeException('No handler available for this file type.');
    }
}
