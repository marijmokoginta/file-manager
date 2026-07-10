<?php

namespace M2code\FileManager\Application;

use RuntimeException;

class UploadCancelledException extends RuntimeException
{
    public function __construct(string $token = '')
    {
        parent::__construct('Upload cancelled'.($token ? ": {$token}" : ''));
    }
}
