<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Uploader\ImageUploader;
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
            ->upload($file, 'testing');

        self::assertNotNull($res);
        self::assertNotNull($res->path);
        self::assertNotNull($res->lowQualityPath);
        self::assertNotNull($res->watermarkPath);
        self::assertNotNull($res->blurhash);
        self::assertNotSame('', $res->blurhash);

        $url = FileUrl::getUrl($res->path);
        $signedUrl = FileUrl::getSignedUrl($res->path, now()->addMinutes(5));

        Storage::disk('public')->assertExists($res->path);
        Storage::disk('public')->assertExists($res->lowQualityPath);
        Storage::disk('public')->assertExists($res->watermarkPath);

        self::assertNotNull($url);
        self::assertNotNull($signedUrl);

        $plainResponse = $this->get($this->toRelativeUri($url));
        $plainResponse->assertOk();
        self::assertStringStartsWith('image/', (string) $plainResponse->headers->get('Content-Type'));

        $signedResponse = $this->get($this->toRelativeUri($signedUrl));
        $signedResponse->assertOk();

        $expiredSignedUrl = FileUrl::getSignedUrl($res->path, now()->subMinute());
        $expiredResponse = $this->get($this->toRelativeUri($expiredSignedUrl));
        $expiredResponse->assertForbidden();
    }

    protected function toRelativeUri(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "$path?$query" : $path;
    }
}
