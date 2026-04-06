<?php

namespace M2code\FileManager\Domain\ValueObjects;

class FileVariants
{
    /** @var array<string, FileVariant> */
    protected array $variants = [];

    public function add(FileVariant $variant): void
    {
        $this->variants[$variant->type] = $variant;
    }

    public function get(string $type): ?FileVariant
    {
        return $this->variants[$type] ?? null;
    }

    public function all(): array
    {
        return array_values($this->variants);
    }

    public function paths(): array
    {
        return array_map(
            static fn (FileVariant $variant) => $variant->path,
            $this->all()
        );
    }
}
