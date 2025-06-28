<?php

namespace M2code\FileManager\Application\Image\Actions;

use Intervention\Image\Colors\Rgb\Channels\Blue;
use Intervention\Image\Colors\Rgb\Channels\Green;
use Intervention\Image\Colors\Rgb\Channels\Red;
use Intervention\Image\Colors\Rgb\Color as RgbColor;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorSpace;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use kornrunner\Blurhash\Blurhash;

class GenerateBlurAction implements ImageAction
{
    public function execute($file): string
    {
        $image = ImageManager::imagick()
            ->read($file)
            ->scaleDown(width: 100);

        return $this->generateBlurhash($image);
    }

    protected function generateBlurhash (ImageInterface $image): string
    {
        $width = $image->width();
        $height = $image->height();

        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; ++$x) {
                $colors = $image->pickColor($x, $y);

                if (!($colors instanceof RgbColor)) {
                    $colors = $colors->convertTo(new RgbColorspace());
                }

                $row[] = [
                    $colors->channel(Red::class)->value(),
                    $colors->channel(Green::class)->value(),
                    $colors->channel(Blue::class)->value()
                ];
            }
            $pixels[] = $row;
        }

        $components_x = 4;
        $components_y = 3;

        return Blurhash::encode($pixels, $components_x, $components_y);
    }
}