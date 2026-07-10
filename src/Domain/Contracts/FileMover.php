<?php

namespace M2code\FileManager\Domain\Contracts;

interface FileMover
{
    /**
     * Move a single file from tmp storage to a permanent location.
     *
     * @param  string  $tmpPath           Full path of the file in tmp storage
     * @param  string  $destinationFolder Target folder on the permanent disk
     * @param  string|null  $disk         Permanent disk name (defaults to configured driver disk)
     * @return string                     New path on the permanent disk
     */
    public function move(string $tmpPath, string $destinationFolder, ?string $disk = null): string;

    /**
     * Move all files within a tmp folder to a permanent location.
     *
     * @param  string  $tmpFolder         Full path of the tmp folder containing files
     * @param  string  $destinationFolder Target folder on the permanent disk
     * @param  string|null  $disk         Permanent disk name (defaults to configured driver disk)
     * @return array<string, string>      Map of [original tmp path => new permanent path]
     */
    public function moveAll(string $tmpFolder, string $destinationFolder, ?string $disk = null): array;
}
