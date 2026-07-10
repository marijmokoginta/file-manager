<?php

namespace M2code\FileManager\tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\UploadCancelledException;
use M2code\FileManager\Application\UploadService;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class UploadServiceTest extends TestCase
{
    protected UploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp');
        Storage::fake('public');

        $this->service = app(UploadService::class);
    }

    #[Test]
    public function it_uploads_image_and_returns_upload_response(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file);

        $this->assertEquals('image', $result->type);
        $this->assertEquals('image/png', $result->mimeType);
        $this->assertEquals('test.png', $result->originalName);
        $this->assertGreaterThan(0, $result->size);
        $this->assertStringStartsWith('tmp/uploads/', $result->tmpFolder);
        $this->assertStringStartsWith('tmp/uploads/', $result->tmpPath);

        // Defaults: blurhash=true, optimize=true
        $this->assertArrayHasKey('blurhash', $result->extra);
        $this->assertNotEmpty($result->extra['blurhash']);
        $this->assertArrayHasKey('optimized_path', $result->extra);

        // Files exist on tmp disk
        Storage::disk('tmp')->assertExists($result->tmpPath);
        Storage::disk('tmp')->assertExists($result->extra['optimized_path']);
    }

    #[Test]
    public function it_uploads_image_with_custom_options(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file, [
            'blurhash' => false,
            'optimize' => false,
            'watermark' => true,
            'low_quality' => true,
        ]);

        $this->assertEquals('image', $result->type);
        $this->assertArrayNotHasKey('blurhash', $result->extra);
        $this->assertArrayNotHasKey('optimized_path', $result->extra);
        $this->assertArrayHasKey('watermark_path', $result->extra);
        $this->assertArrayHasKey('low_quality_path', $result->extra);
    }

    #[Test]
    public function it_ignores_unknown_options(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file, [
            'unknown' => true,
            'optimize' => false,
        ]);

        $this->assertEquals('image', $result->type);
        $this->assertArrayNotHasKey('optimized_path', $result->extra);
        $this->assertArrayNotHasKey('unknown', $result->extra);
    }

    #[Test]
    public function it_uploads_svg_file(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20"/></svg>';
        $file = UploadedFile::fake()->createWithContent('icon.svg', $svg);

        $result = $this->service->upload($file);

        $this->assertEquals('svg', $result->type);
        $this->assertEquals('image/svg+xml', $result->mimeType);
        $this->assertEmpty($result->extra);
        Storage::disk('tmp')->assertExists($result->tmpPath);
    }

    #[Test]
    public function it_uploads_pdf_file(): void
    {
        $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $result = $this->service->upload($pdf);

        $this->assertEquals('document', $result->type);
        $this->assertEquals('application/pdf', $result->mimeType);
        $this->assertEmpty($result->extra);
        Storage::disk('tmp')->assertExists($result->tmpPath);
    }

    #[Test]
    public function it_generates_unique_folder_per_request(): void
    {
        $file1 = UploadedFile::fake()->image('a.png', 10, 10);
        $file2 = UploadedFile::fake()->image('b.png', 10, 10);

        $result1 = $this->service->upload($file1);
        $result2 = $this->service->upload($file2);

        $this->assertNotEquals($result1->tmpFolder, $result2->tmpFolder);
    }

    #[Test]
    public function it_validates_file_size(): void
    {
        config()->set('file-manager.validation.max_file_size.document', 1); // 1 KiB

        $tmpPath = tempnam(sys_get_temp_dir(), 'fm_test_');
        file_put_contents($tmpPath, "%PDF-1.4\n".random_bytes(5 * 1024));
        $file = new UploadedFile($tmpPath, 'large.pdf', 'application/pdf', null, true);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/exceeds the maximum allowed size/');

            $this->service->upload($file);
        } finally {
            @unlink($tmpPath);
        }
    }

    #[Test]
    public function it_rejects_unsupported_file_type(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsupported file type/');

        $this->service->upload($file);
    }

    #[Test]
    public function it_serializes_response_to_json(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file);
        $json = json_encode($result);

        $this->assertNotFalse($json);

        $data = json_decode($json, true);

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('tmp_path', $data);
        $this->assertArrayHasKey('tmp_folder', $data);
        $this->assertArrayHasKey('original_name', $data);
        $this->assertArrayHasKey('size', $data);
        $this->assertArrayHasKey('mime_type', $data);
        $this->assertArrayHasKey('extra', $data);
    }

    #[Test]
    public function image_default_options_can_be_overridden_via_config(): void
    {
        config()->set('file-manager.upload.default_options.image', [
            'optimize' => false,
            'blurhash' => false,
            'watermark' => true,
            'low_quality' => false,
        ]);

        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file);

        $this->assertArrayNotHasKey('blurhash', $result->extra);
        $this->assertArrayNotHasKey('optimized_path', $result->extra);
        $this->assertArrayHasKey('watermark_path', $result->extra);
    }

    #[Test]
    public function it_saves_all_files_to_tmp_disk_not_public(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file, [
            'watermark' => true,
            'optimize' => false,
        ]);

        Storage::disk('tmp')->assertExists($result->tmpPath);
        Storage::disk('tmp')->assertExists($result->extra['watermark_path']);

        Storage::disk('public')->assertMissing($result->tmpPath);
        Storage::disk('public')->assertMissing($result->extra['watermark_path']);
    }

    // ── Cancel Token ──────────────────────────────────────────────

    #[Test]
    public function it_throws_when_cancel_token_is_marked_cancelled(): void
    {
        $token = 'my-cancel-token';
        $this->service->cancel($token);

        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $this->expectException(UploadCancelledException::class);
        $this->service->upload($file, [], $token);
    }

    #[Test]
    public function it_allows_upload_when_token_not_cancelled(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file, [], 'fresh-token');

        $this->assertEquals('image', $result->type);
    }

    #[Test]
    public function it_allows_upload_without_token(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $result = $this->service->upload($file);

        $this->assertEquals('image', $result->type);
    }

    #[Test]
    public function is_cancelled_returns_false_for_unknown_token(): void
    {
        $this->assertFalse($this->service->isCancelled('unknown-token'));
    }

    #[Test]
    public function is_cancelled_returns_true_for_cancelled_token(): void
    {
        $token = 'will-be-cancelled';
        $this->service->cancel($token);

        $this->assertTrue($this->service->isCancelled($token));
    }
}
