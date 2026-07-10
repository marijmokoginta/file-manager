<?php

namespace M2code\FileManager\Application\Image\Actions;

use Imagick;
use Intervention\Image\ImageManager;
use Intervention\Image\MediaType;
use Throwable;

class GenerateOptimizedImageAction
{
    public function execute($file, string $format = 'avif'): ?string
    {
        $mediaType = $this->resolveOutputMediaType($format);
        if ($mediaType === null) {
            return null;
        }

        $image = ImageManager::imagick()->read($file);
        $encoded = $image->encodeByMediaType($mediaType, quality: 60);

        return $encoded->toDataUri();
    }

    protected function resolveOutputMediaType(string $format): ?MediaType
    {
        $format = strtolower($format);

        if ($format === 'avif') {
            if ($this->supportsAvif()) {
                return MediaType::IMAGE_AVIF;
            }

            if ($this->supportsWebp()) {
                return MediaType::IMAGE_WEBP;
            }

            return null;
        }

        if ($format === 'webp') {
            return $this->supportsWebp() ? MediaType::IMAGE_WEBP : null;
        }

        return null;
    }

    protected function supportsAvif(): bool
    {
        return $this->supportsImagickFormat('AVIF');
    }

    protected function supportsWebp(): bool
    {
        return $this->supportsImagickFormat('WEBP');
    }

    protected function supportsImagickFormat(string $format): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(Imagick::class)) {
            return false;
        }

        try {
            return ! empty(Imagick::queryFormats($format));
        } catch (Throwable) {
            return false;
        }
    }
}
