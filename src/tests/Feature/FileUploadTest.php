<?php

namespace Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\tests\TestCase;
use function PHPUnit\Framework\assertNotNull;

class FileUploadTest extends TestCase
{
    public function test_image_upload(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('dummy.png');
        $res = ImageUploader::make()
            ->enableBlur()
            ->enableWatermark()
            ->enableLowQuality()
            ->upload($file, 'testing');

        assertNotNull($res);
        assertNotNull($res->path);
        assertNotNull($res->lowQualityPath);
        assertNotNull($res->blurhash);
    }
}