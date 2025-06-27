<?php

namespace M2code\FileManager\Application;

use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\DTO\FileOperationResult;

class UploadFile
{
    protected $saver;

    public function __construct(FileSaver $saver){
        $this->saver = $saver;
    }

    public function execute($file, string $folder): FileOperationResult
    {
        return $this->saver->save($file, $folder);
    }
}