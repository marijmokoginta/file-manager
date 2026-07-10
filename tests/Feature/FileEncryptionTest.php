<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use M2code\FileManager\Drivers\Local\LocalFileSaver;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Facades\FileUrl;
use M2code\FileManager\Infrastructure\Encryption\LaravelCryptEncryptor;
use M2code\FileManager\Infrastructure\Encryption\NoopEncryptor;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileEncryptionTest extends TestCase
{
    private ContentEncryptor $encryptor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->encryptor = new LaravelCryptEncryptor;
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Enable encryption for these tests
        $app['config']->set('file-manager.encryption', [
            'enabled' => true,
            'driver' => 'laravel',
        ]);
    }

    // ── FileManager save with encryption ─────────────────────────────

    #[Test]
    public function it_saves_encrypted_file_content(): void
    {
        $file = UploadedFile::fake()->image('dummy.png', 10, 10);

        $result = FileManager::save($file, 'testing');

        Storage::disk('public')->assertExists($result->filePath);

        // The stored content should NOT be the original image content
        $stored = Storage::disk('public')->get($result->filePath);
        $original = file_get_contents($file->getRealPath());

        $this->assertNotSame($original, $stored);
        $this->assertStringStartsWith('eyJ', $stored); // Laravel Crypt base64 starts with eyJ
    }

    #[Test]
    public function it_serves_encrypted_file_as_plaintext(): void
    {
        $file = UploadedFile::fake()->image('dummy.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        $url = FileUrl::getUrl($result->filePath);
        $response = $this->get($this->toRelativeUri($url));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');

        // The response should be the original plaintext (decrypted)
        $original = file_get_contents($file->getRealPath());
        $this->assertSame($original, $response->getContent());
    }

    #[Test]
    public function it_can_disable_encryption_per_save(): void
    {
        $file = UploadedFile::fake()->image('dummy.png', 10, 10);

        // Override: disable encryption for this specific save
        $result = FileManager::save($file, 'testing', encrypted: false);

        $stored = Storage::disk('public')->get($result->filePath);
        $original = file_get_contents($file->getRealPath());

        $this->assertSame($original, $stored);
    }

    // ── ImageUploader with encryption ────────────────────────────────

    #[Test]
    public function image_uploader_encrypts_files_when_enabled(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 50, 50);

        $result = ImageUploader::make()
            ->encrypted()
            ->upload($file, 'photos');

        $originalPath = $result->variants->get('original')?->path;
        $this->assertNotNull($originalPath);

        // Original file should be encrypted on disk
        $stored = Storage::disk('public')->get($originalPath);
        $original = file_get_contents($file->getRealPath());

        $this->assertNotSame($original, $stored);
    }

    #[Test]
    public function image_uploader_can_disable_encryption_per_upload(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 50, 50);

        $result = ImageUploader::make()
            ->encrypted(false)
            ->upload($file, 'photos');

        $originalPath = $result->variants->get('original')?->path;
        $this->assertNotNull($originalPath);

        // File should be plaintext
        $stored = Storage::disk('public')->get($originalPath);
        $original = file_get_contents($file->getRealPath());

        $this->assertSame($original, $stored);
    }

    #[Test]
    public function image_uploader_with_encrypted_variants_are_also_encrypted(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 50, 50);

        $result = ImageUploader::make()
            ->lowQuality()
            ->encrypted()
            ->upload($file, 'photos');

        // Both original and low_quality should be encrypted
        $originalPath = $result->variants->get('original')?->path;
        $lowQualityPath = $result->variants->get('low_quality')?->path;

        $this->assertNotNull($originalPath);
        $this->assertNotNull($lowQualityPath);

        $original = file_get_contents($file->getRealPath());

        $originalStored = Storage::disk('public')->get($originalPath);
        $this->assertNotSame($original, $originalStored);

        $lowStored = Storage::disk('public')->get($lowQualityPath);
        $this->assertNotNull($lowStored);
    }

    // ── NoopEncryptor fallback (file saved without encryption) ───────

    #[Test]
    public function noop_encryptor_saves_plaintext(): void
    {
        // Temporarily disable encryption
        $this->app['config']->set('file-manager.encryption.enabled', false);

        $saver = new LocalFileSaver(
            config: ['disk' => 'public'],
            encryptor: new NoopEncryptor,
        );

        $file = UploadedFile::fake()->image('dummy.png', 10, 10);
        $result = $saver->save($file, 'testing');

        $stored = Storage::disk('public')->get($result->filePath);
        $original = file_get_contents($file->getRealPath());

        $this->assertSame($original, $stored);
    }

    // ── OpenSslEncryptor integration ─────────────────────────────────

    #[Test]
    public function it_works_with_openssl_encryptor(): void
    {
        $saver = new LocalFileSaver(
            config: ['disk' => 'public'],
            encryptor: new \M2code\FileManager\Infrastructure\Encryption\OpenSslEncryptor(
                'test-secret-key-for-testing-only-123456'
            ),
        );

        $file = UploadedFile::fake()->image('dummy.png', 10, 10);
        $result = $saver->save($file, 'testing', encrypted: true);

        $stored = Storage::disk('public')->get($result->filePath);
        $original = file_get_contents($file->getRealPath());

        $this->assertNotSame($original, $stored);

        // Decrypt should return original
        $encryptor = new \M2code\FileManager\Infrastructure\Encryption\OpenSslEncryptor(
            'test-secret-key-for-testing-only-123456'
        );
        $decrypted = $encryptor->decrypt($stored);
        $this->assertSame($original, $decrypted);
    }

    // ── Backward compatibility: encryption disabled ──────────────────

    #[Test]
    public function backward_compatible_without_encryption(): void
    {
        // Reset encryption to disabled
        $this->app['config']->set('file-manager.encryption.enabled', false);

        $file = UploadedFile::fake()->image('dummy.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        $stored = Storage::disk('public')->get($result->filePath);
        $original = file_get_contents($file->getRealPath());

        $this->assertSame($original, $stored);
    }

    protected function toRelativeUri(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "$path?$query" : $path;
    }
}
