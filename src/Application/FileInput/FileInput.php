<?php

namespace M2code\FileManager\Application\FileInput;

interface FileInput
{
    public function getContent(): string;

    public function getMimeType(): string;

    public function getExtension(): string;
}
