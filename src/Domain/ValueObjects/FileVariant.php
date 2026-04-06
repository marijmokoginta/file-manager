<?php

namespace M2code\FileManager\Domain\ValueObjects;

readonly class FileVariant
{
    public function __construct(
        public string $type,
        public string $path
    ) {}
}
