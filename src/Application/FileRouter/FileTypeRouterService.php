<?php

namespace M2code\FileManager\Application\FileRouter;

use Illuminate\Support\Collection;
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

    public function save($file, string $folder): FileOperationResult
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($file)) {
                return $handler->handleSave($file, $folder);
            }
        }

        throw new RuntimeException('No handler available for this file type.');
    }
}