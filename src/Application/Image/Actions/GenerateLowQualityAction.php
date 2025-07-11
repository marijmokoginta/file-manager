<?php

namespace M2code\FileManager\Application\Image\Actions;

use Intervention\Image\ImageManager;
use Intervention\Image\MediaType;

class GenerateLowQualityAction implements ImageAction
{
    public function execute($file): string
    {
        $image = ImageManager::imagick()
            ->read($file)
            ->scaleDown(width: 300);

        $encoded = $image->encodeByMediaType(MediaType::IMAGE_JPEG, quality: 10);

        return $encoded->toDataUri();
    }
}