<?php

namespace M2code\FileManager\Application\Image;

use M2code\FileManager\Application\Image\Actions\GenerateBlurAction;
use M2code\FileManager\Application\Image\Actions\ApplyWatermarkAction;
use M2code\FileManager\Application\Image\Actions\GenerateLowQualityAction;
use M2code\FileManager\Domain\ValueObjects\FileVariant;
use M2code\FileManager\Domain\ValueObjects\FileVariants;

class ImageProcessor
{
    protected bool $shouldBlur = false;
    protected bool $shouldLowQuality = false;
    protected bool $shouldWatermark = false;
    protected ?string $blurhash = null;

    public function __construct(
        protected GenerateBlurAction $blurAction,
        protected GenerateLowQualityAction $lowQualityAction,
        protected ApplyWatermarkAction $watermarkAction
    ) {}

    public function withBlur(bool $shouldBlur): self { $this->shouldBlur = $shouldBlur; return $this; }
    public function withLowQuality(bool $shouldLowQuality): self { $this->shouldLowQuality = $shouldLowQuality; return $this; }
    public function withWatermark(bool $shouldWatermark): self { $this->shouldWatermark = $shouldWatermark; return $this; }

    public function process($file): FileVariants
    {
        $this->blurhash = $this->shouldBlur
            ? $this->blurAction->execute($file)
            : null;

        $variants = new FileVariants();

        if ($this->shouldLowQuality) {
            $variants->add(new FileVariant(
                'low_quality',
                $this->lowQualityAction->execute($file)
            ));
        }

        if ($this->shouldWatermark) {
            $variants->add(new FileVariant(
                'watermark',
                $this->watermarkAction->execute($file)
            ));
        }

        return $variants;
    }

    public function getBlurhash(): ?string
    {
        return $this->blurhash;
    }

    public function reset(): self
    {
        $this->shouldBlur = false;
        $this->shouldLowQuality = false;
        $this->shouldWatermark = false;
        $this->blurhash = null;

        return $this;
    }
}
