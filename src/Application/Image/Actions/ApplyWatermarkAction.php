<?php

namespace M2code\FileManager\Application\Image\Actions;

use Intervention\Image\ImageManager;
use Intervention\Image\MediaType;

class ApplyWatermarkAction implements ImageAction
{
    public function execute($file): string
    {
        $manager = ImageManager::imagick();
        $image = $manager->read($file);

        $watermark = $manager
            ->create(220, 60)
            ->fill('ffffff');

        $image->place($watermark, 'bottom-right', 20, 20, 30);

        return $image
            ->encodeByMediaType(MediaType::IMAGE_JPEG, quality: 82)
            ->toDataUri();
    }
}
