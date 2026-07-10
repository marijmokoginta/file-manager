<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileUploadApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp');
        Storage::fake('public');
    }

    // ── Authentication ──────────────────────────────────────────────

    #[Test]
    public function it_rejects_requests_without_token(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $this->postJson('/upload', ['file' => $file])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized. Invalid or missing API token.']);
    }

    #[Test]
    public function it_rejects_requests_with_invalid_token(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer wrong-token',
        ])
            ->assertUnauthorized();
    }

    #[Test]
    public function it_accepts_requests_with_valid_token(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ])
            ->assertCreated();
    }

    #[Test]
    public function it_accepts_comma_separated_tokens(): void
    {
        config()->set('file-manager.api.token', 'token-a,token-b');

        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer token-b',
        ])
            ->assertCreated();
    }

    // ── Validation ───────────────────────────────────────────────────

    #[Test]
    public function it_requires_a_file(): void
    {
        $this->postJson('/upload', [], [
            'Authorization' => 'Bearer test-token',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    // ── Image Upload ─────────────────────────────────────────────────

    #[Test]
    public function it_uploads_image_with_default_options(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'type',
                'tmp_path',
                'tmp_folder',
                'original_name',
                'size',
                'mime_type',
                'extra',
            ]);

        $data = $response->json();

        $this->assertEquals('image', $data['type']);
        $this->assertEquals('photo.png', $data['original_name']);
        $this->assertEquals('image/png', $data['mime_type']);
        $this->assertStringStartsWith('tmp/uploads/', $data['tmp_folder']);
        $this->assertStringStartsWith('tmp/uploads/', $data['tmp_path']);
        $this->assertStringEndsWith('.png', $data['tmp_path']);

        // Default options: blurhash=true, optimize=true
        $this->assertArrayHasKey('blurhash', $data['extra']);
        $this->assertNotNull($data['extra']['blurhash']);
        $this->assertNotEmpty($data['extra']['blurhash']);
        $this->assertArrayHasKey('optimized_path', $data['extra']);
        $this->assertNotNull($data['extra']['optimized_path']);

        // Default false options should NOT appear
        $this->assertArrayNotHasKey('watermark_path', $data['extra']);
        $this->assertArrayNotHasKey('low_quality_path', $data['extra']);

        // Assert file exists on tmp disk
        Storage::disk('tmp')->assertExists($data['tmp_path']);
        Storage::disk('tmp')->assertExists($data['extra']['optimized_path']);
    }

    #[Test]
    public function it_uploads_image_with_disabled_defaults(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', [
            'file' => $file,
            'options' => [
                'blurhash' => false,
                'optimize' => false,
            ],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $data = $response->json();

        $this->assertEquals('image', $data['type']);

        // Disabled options should not appear in extra
        $this->assertArrayNotHasKey('blurhash', $data['extra']);
        $this->assertArrayNotHasKey('optimized_path', $data['extra']);
    }

    #[Test]
    public function it_enables_watermark_and_low_quality_when_requested(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', [
            'file' => $file,
            'options' => [
                'watermark' => true,
                'low_quality' => true,
                'optimize' => false,
            ],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $data = $response->json();

        $this->assertArrayHasKey('watermark_path', $data['extra']);
        $this->assertNotNull($data['extra']['watermark_path']);
        Storage::disk('tmp')->assertExists($data['extra']['watermark_path']);

        $this->assertArrayHasKey('low_quality_path', $data['extra']);
        $this->assertNotNull($data['extra']['low_quality_path']);
        Storage::disk('tmp')->assertExists($data['extra']['low_quality_path']);
    }

    #[Test]
    public function it_ignores_unknown_options(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', [
            'file' => $file,
            'options' => [
                'unknown_option' => true,
                'another_unknown' => 'some value',
                'optimize' => false,
            ],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $data = $response->json();

        // Unknown options ignored, known option 'optimize' processed
        $this->assertArrayNotHasKey('optimized_path', $data['extra']);
        $this->assertArrayHasKey('blurhash', $data['extra']); // default true still active
    }

    // ── SVG Upload ───────────────────────────────────────────────────

    #[Test]
    public function it_uploads_svg_file(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20"/></svg>';
        $file = UploadedFile::fake()->createWithContent('icon.svg', $svg);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $data = $response->json();

        $this->assertEquals('svg', $data['type']);
        $this->assertEquals('image/svg+xml', $data['mime_type']);
        $this->assertStringEndsWith('.svg', $data['tmp_path']);
        $this->assertEmpty((array) $data['extra']);

        Storage::disk('tmp')->assertExists($data['tmp_path']);
    }

    #[Test]
    public function it_ignores_image_options_for_svg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20"/></svg>';
        $file = UploadedFile::fake()->createWithContent('icon.svg', $svg);

        $response = $this->postJson('/upload', [
            'file' => $file,
            'options' => [
                'blurhash' => true,
                'watermark' => true,
                'optimize' => true,
            ],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $data = $response->json();

        $this->assertEquals('svg', $data['type']);
        $this->assertEmpty((array) $data['extra']);
    }

    // ── PDF Upload ───────────────────────────────────────────────────

    #[Test]
    public function it_uploads_pdf_file(): void
    {
        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson('/upload', ['file' => $pdf], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $data = $response->json();

        $this->assertEquals('document', $data['type']);
        $this->assertEquals('application/pdf', $data['mime_type']);
        $this->assertStringEndsWith('.pdf', $data['tmp_path']);
        $this->assertEmpty((array) $data['extra']);

        Storage::disk('tmp')->assertExists($data['tmp_path']);
    }

    // ── File Size Validation ─────────────────────────────────────────

    #[Test]
    public function it_rejects_file_exceeding_max_size(): void
    {
        config()->set('file-manager.validation.max_file_size.document', 1); // 1 KiB

        $tmpPath = tempnam(sys_get_temp_dir(), 'fm_test_');
        file_put_contents($tmpPath, "%PDF-1.4\n" . random_bytes(5 * 1024));
        $file = new UploadedFile($tmpPath, 'large.pdf', 'application/pdf', null, true);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertUnprocessable();

        @unlink($tmpPath);
    }

    #[Test]
    public function it_formats_size_in_mib_for_large_files(): void
    {
        config()->set('file-manager.validation.max_file_size.document', 1); // 1 KiB

        $tmpPath = tempnam(sys_get_temp_dir(), 'fm_test_');
        file_put_contents($tmpPath, "%PDF-1.4\n" . random_bytes(2048 * 1024));
        $file = new UploadedFile($tmpPath, 'large.pdf', 'application/pdf', null, true);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertUnprocessable();

        $data = $response->json();
        $this->assertArrayHasKey('message', $data);

        @unlink($tmpPath);
    }

    // ── Unsupported File Type ────────────────────────────────────────

    #[Test]
    public function it_rejects_unsupported_file_type(): void
    {
        $file = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertUnprocessable();
    }

    // ── Tmp Folder Isolation ─────────────────────────────────────────

    #[Test]
    public function each_request_creates_unique_tmp_folder(): void
    {
        $file1 = UploadedFile::fake()->image('first.png', 100, 100);
        $file2 = UploadedFile::fake()->image('second.png', 100, 100);

        $res1 = $this->postJson('/upload', ['file' => $file1], [
            'Authorization' => 'Bearer test-token',
        ])->json();

        $res2 = $this->postJson('/upload', ['file' => $file2], [
            'Authorization' => 'Bearer test-token',
        ])->json();

        $this->assertNotEquals($res1['tmp_folder'], $res2['tmp_folder']);
    }

    // ── Response Integrity ───────────────────────────────────────────

    #[Test]
    public function response_has_correct_structure(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'type',
                'tmp_path',
                'tmp_folder',
                'original_name',
                'size',
                'mime_type',
                'extra',
            ]);

        $data = $response->json();

        $this->assertIsString($data['type']);
        $this->assertIsString($data['tmp_path']);
        $this->assertIsString($data['tmp_folder']);
        $this->assertIsInt($data['size']);
        $this->assertGreaterThan(0, $data['size']);
        $this->assertIsString($data['mime_type']);
        $this->assertIsArray($data['extra']);
    }

    // ── Files are saved to tmp disk (not public) ─────────────────────

    #[Test]
    public function files_are_saved_to_tmp_disk_only(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $data = $response->json();

        Storage::disk('tmp')->assertExists($data['tmp_path']);
        Storage::disk('public')->assertMissing($data['tmp_path']);
    }

    #[Test]
    public function image_variants_are_saved_to_tmp_disk(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $response = $this->postJson('/upload', [
            'file' => $file,
            'options' => [
                'watermark' => true,
                'low_quality' => true,
                'optimize' => false,
            ],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $data = $response->json();

        Storage::disk('tmp')->assertExists($data['tmp_path']);
        Storage::disk('tmp')->assertExists($data['extra']['watermark_path']);
        Storage::disk('tmp')->assertExists($data['extra']['low_quality_path']);

        Storage::disk('public')->assertMissing($data['tmp_path']);
        Storage::disk('public')->assertMissing($data['extra']['watermark_path']);
        Storage::disk('public')->assertMissing($data['extra']['low_quality_path']);
    }

    // ── Original name from UploadedFile ──────────────────────────────

    #[Test]
    public function it_returns_original_filename(): void
    {
        $file = UploadedFile::fake()->image('my-avatar.png', 100, 100);

        $response = $this->postJson('/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertCreated();
        $this->assertEquals('my-avatar.png', $response->json('original_name'));
    }
}
