<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Image\Actions\GenerateOptimizedImageAction;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class OptimizedVariantTest extends TestCase
{
    #[Test]
    public function test_it_generates_avif_when_supported(): void
    {
        if (!$this->supportsImagickFormat('AVIF')) {
            $this->markTestSkipped('AVIF is not supported by Imagick in this environment.');
        }

        $action = new GenerateOptimizedImageAction();
        $file = UploadedFile::fake()->image('source.png', 160, 160);
        $result = $action->execute($file, 'avif');

        self::assertNotNull($result);
        self::assertStringStartsWith('data:image/avif;base64,', $result);
    }

    #[Test]
    public function test_it_falls_back_safely_when_avif_and_webp_are_unsupported(): void
    {
        $action = new class extends GenerateOptimizedImageAction {
            protected function supportsAvif(): bool
            {
                return false;
            }

            protected function supportsWebp(): bool
            {
                return false;
            }
        };

        $file = UploadedFile::fake()->image('source.png', 160, 160);
        $result = $action->execute($file, 'avif');

        self::assertNull($result);
    }

    #[Test]
    public function test_uploader_stores_optimized_variant_when_enabled_if_supported(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('source.png', 160, 160);
        $result = ImageUploader::make()
            ->optimize('avif')
            ->upload($file, 'testing');

        $optimized = $result->variants->get('optimized');

        if ($this->supportsImagickFormat('AVIF') || $this->supportsImagickFormat('WEBP')) {
            self::assertNotNull($optimized);
            self::assertNotNull($result->optimizedPath);
            Storage::disk('public')->assertExists($result->optimizedPath);
        } else {
            self::assertNull($optimized);
            self::assertNull($result->optimizedPath);
        }
    }

    protected function supportsImagickFormat(string $format): bool
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return !empty(\Imagick::queryFormats($format));
        } catch (Throwable) {
            return false;
        }
    }
}
