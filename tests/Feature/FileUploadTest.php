<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Facades\FileUrl;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileUploadTest extends TestCase
{
    #[Test]
    public function test_image_upload_generates_variants_and_urls(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('dummy.png', 100, 100);
        $res = ImageUploader::make()
            ->blur()
            ->watermark()
            ->lowQuality()
            ->optimize('avif')
            ->upload($file, 'testing');

        self::assertNotNull($res);
        self::assertNotNull($res->blurhash);
        self::assertNotSame('', $res->blurhash);
        self::assertNotNull($res->variants->get('original'));
        self::assertNotNull($res->variants->get('low_quality'));
        self::assertNotNull($res->variants->get('watermark'));

        $originalPath = $res->variants->get('original')?->path;
        $lowQualityPath = $res->variants->get('low_quality')?->path;
        $watermarkPath = $res->variants->get('watermark')?->path;
        $optimizedPath = $res->variants->get('optimized')?->path;

        self::assertNotNull($originalPath);
        self::assertNotNull($lowQualityPath);
        self::assertNotNull($watermarkPath);

        // Backward compatibility for legacy fields.
        self::assertSame($originalPath, $res->path);
        self::assertSame($lowQualityPath, $res->lowQualityPath);
        self::assertSame($watermarkPath, $res->watermarkPath);
        self::assertSame($optimizedPath, $res->optimizedPath);

        $url = FileUrl::getUrl($originalPath);
        $signedUrl = FileUrl::getSignedUrl($originalPath, now()->addMinutes(5));

        Storage::disk('public')->assertExists($originalPath);
        Storage::disk('public')->assertExists($lowQualityPath);
        Storage::disk('public')->assertExists($watermarkPath);
        if ($optimizedPath !== null) {
            Storage::disk('public')->assertExists($optimizedPath);
        }

        self::assertNotNull($url);
        self::assertNotNull($signedUrl);

        $plainResponse = $this->get($this->toRelativeUri($url));
        $plainResponse->assertOk();
        self::assertStringStartsWith('image/', (string) $plainResponse->headers->get('Content-Type'));

        $signedResponse = $this->get($this->toRelativeUri($signedUrl));
        $signedResponse->assertOk();

        $expiredSignedUrl = FileUrl::getSignedUrl($originalPath, now()->subMinute());
        $expiredResponse = $this->get($this->toRelativeUri($expiredSignedUrl));
        $expiredResponse->assertForbidden();

        $deleteResults = FileManager::deleteVariants($res->variants);
        self::assertSame(array_fill_keys($res->variants->paths(), true), $deleteResults);

        foreach ($res->variants->paths() as $variantPath) {
            Storage::disk('public')->assertMissing($variantPath);
        }
    }

    #[Test]
    public function test_svg_upload_skips_image_processing_variants(): void
    {
        Storage::fake('public');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20"/></svg>';

        $res = ImageUploader::make()
            ->blur()
            ->watermark()
            ->lowQuality()
            ->optimize('avif')
            ->upload($svg, 'testing');

        self::assertNotNull($res->variants->get('original'));
        self::assertNull($res->variants->get('low_quality'));
        self::assertNull($res->variants->get('watermark'));
        self::assertNull($res->variants->get('optimized'));
        self::assertNull($res->blurhash);

        $originalPath = $res->variants->get('original')?->path;
        self::assertNotNull($originalPath);
        self::assertStringEndsWith('.svg', $originalPath);
        Storage::disk('public')->assertExists($originalPath);
    }

    #[Test]
    public function test_base64_png_upload_runs_raster_processing(): void
    {
        Storage::fake('public');

        $png = UploadedFile::fake()->image('from-base64.png', 64, 64);
        $realPath = $png->getRealPath();
        self::assertNotFalse($realPath);

        $raw = file_get_contents($realPath);
        self::assertNotFalse($raw);

        $base64Png = 'data:image/png;base64,'.base64_encode($raw);

        $res = ImageUploader::make()
            ->blur()
            ->lowQuality()
            ->upload($base64Png, 'testing');

        $original = $res->variants->get('original')?->path;
        $lowQuality = $res->variants->get('low_quality')?->path;

        self::assertNotNull($original);
        self::assertNotNull($lowQuality);
        self::assertNotNull($res->blurhash);
        self::assertNotSame('', $res->blurhash);

        Storage::disk('public')->assertExists($original);
        Storage::disk('public')->assertExists($lowQuality);
    }

    #[Test]
    public function test_base64_svg_upload_skips_image_processing_variants(): void
    {
        Storage::fake('public');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect width="16" height="16"/></svg>';
        $base64Svg = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $res = ImageUploader::make()
            ->blur()
            ->watermark()
            ->lowQuality()
            ->optimize('avif')
            ->upload($base64Svg, 'testing');

        self::assertNotNull($res->variants->get('original'));
        self::assertNull($res->variants->get('low_quality'));
        self::assertNull($res->variants->get('watermark'));
        self::assertNull($res->variants->get('optimized'));
        self::assertNull($res->blurhash);

        $originalPath = $res->variants->get('original')?->path;
        self::assertNotNull($originalPath);
        self::assertStringEndsWith('.svg', $originalPath);
        Storage::disk('public')->assertExists($originalPath);
    }

    protected function toRelativeUri(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "$path?$query" : $path;
    }
}
