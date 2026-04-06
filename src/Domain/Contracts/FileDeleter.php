<?php

namespace M2code\FileManager\Domain\Contracts;

interface FileDeleter
{
    public function delete(string $path): bool;

    public function deleteMany(array $paths): array;
}
