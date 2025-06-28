<?php

namespace M2code\FileManager\Application\Image;

use M2code\FileManager\Application\Image\Actions\GenerateBlurAction;
use M2code\FileManager\Application\Image\Actions\ApplyWatermarkAction;
use M2code\FileManager\Application\Image\Actions\GenerateLowQualityAction;
use M2code\FileManager\Application\Image\DTO\ProcessedImageVariants;

class ImageProcessor
{
    protected bool $shouldBlur = false;
    protected bool $shouldLowQuality = false;
    protected bool $shouldWatermark = false;

    public function __construct(
        protected GenerateBlurAction $blurAction,
        protected GenerateLowQualityAction $lowQualityAction,
        protected ApplyWatermarkAction $watermarkAction
    ) {}

    public function withBlur(bool $shouldBlur): self { $this->shouldBlur = $shouldBlur; return $this; }
    public function withLowQuality(bool $shouldLowQuality): self { $this->shouldLowQuality = $shouldLowQuality; return $this; }
    public function withWatermark(bool $shouldWatermark): self { $this->shouldWatermark = $shouldWatermark; return $this; }

    public function process($file): ProcessedImageVariants
    {
        $blurPath = $this->shouldBlur
            ? $this->blurAction->execute($file)
            : null;

        $lowFile = $this->shouldLowQuality
            ? $this->lowQualityAction->execute($file)
            : null;

        $watermarkPath = $this->shouldWatermark
            ? $this->watermarkAction->execute($file)
            : null;

        return new ProcessedImageVariants(
            $blurPath,
            $lowFile,
            $watermarkPath
        );
    }

    public function reset(): self
    {
        $this->shouldBlur = false;
        $this->shouldLowQuality = false;
        $this->shouldWatermark = false;
        return $this;
    }
}
