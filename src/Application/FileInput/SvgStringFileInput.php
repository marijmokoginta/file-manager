<?php

namespace M2code\FileManager\Application\FileInput;

use RuntimeException;

class SvgStringFileInput implements FileInput
{
    public function __construct(protected string $svg)
    {
        if (stripos($svg, '<svg') === false) {
            throw new RuntimeException('Invalid SVG string input.');
        }
    }

    public function getContent(): string
    {
        return $this->svg;
    }

    public function getMimeType(): string
    {
        return 'image/svg+xml';
    }

    public function getExtension(): string
    {
        return 'svg';
    }
}
