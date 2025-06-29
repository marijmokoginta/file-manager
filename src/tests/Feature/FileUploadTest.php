<?php

namespace Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Facades\FileUrl;
use M2code\FileManager\tests\TestCase;
use function PHPUnit\Framework\assertNotNull;

class FileUploadTest extends TestCase
{
    public function test_image_upload(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('dummy.png', 100, 100);
        $res = ImageUploader::make()
            ->enableBlur()
            ->enableWatermark()
            ->enableLowQuality()
            ->upload($file, 'testing');

        assertNotNull($res);
        assertNotNull($res->path);
        assertNotNull($res->lowQualityPath);
        assertNotNull($res->blurhash);

        $url = FileUrl::getUrl($res->path);
        $signedUrl = FileUrl::getSignedUrl($res->path, now()->addMinutes(5));

        self::assertNotNull($url);
        self::assertNotNull($signedUrl);
    }
}