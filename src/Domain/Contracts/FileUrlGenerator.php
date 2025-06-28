<?php

namespace M2code\FileManager\Domain\Contracts;

interface FileUrlGenerator
{
    public function generate(string $path): string;
}