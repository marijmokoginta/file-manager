<?php

namespace M2code\FileManager\Domain\Contracts;

interface FileUrlGenerator
{
    public function getUrl(string $path): string;
}