<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Facades\FileUrl;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileUrlHashTest extends TestCase
{
    private string $testPath = 'testing/photo.png';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ── Without url.secret (legacy base64 mode) ──────────────────────

    #[Test]
    public function get_url_uses_base64_when_no_secret_configured(): void
    {
        $url = FileUrl::getUrl($this->testPath);

        $this->assertStringContainsString(base64_encode($this->testPath), $url);
    }

    #[Test]
    public function serve_works_with_base64_path(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        $url = FileUrl::getUrl($result->filePath);
        $response = $this->get($this->toRelativeUri($url));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }

    // ── With url.secret (encrypted path mode) ────────────────────────

    #[Test]
    public function get_url_uses_encrypted_path_when_secret_is_set(): void
    {
        $this->app['config']->set('file-manager.url.secret', 'test-secret-key');

        $url = FileUrl::getUrl($this->testPath);

        // Should NOT contain base64 of the path
        $this->assertStringNotContainsString(base64_encode($this->testPath), $url);

        // Should not be plain base64 (doesn't start with the base64 of 'testing/')
        $this->assertStringNotContainsString('dGVzdGluZy8', $url);
    }

    #[Test]
    public function serve_works_with_encrypted_path(): void
    {
        $this->app['config']->set('file-manager.url.secret', 'test-secret-key');

        $file = UploadedFile::fake()->image('photo.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        $url = FileUrl::getUrl($result->filePath);
        $response = $this->get($this->toRelativeUri($url));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }

    // ── Backward compatibility: old base64 URLs still work ──────────

    #[Test]
    public function backward_compatible_with_base64_urls(): void
    {
        // Enable encryption
        $this->app['config']->set('file-manager.url.secret', 'test-secret-key');

        $file = UploadedFile::fake()->image('photo.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        // Manually create a legacy base64 URL
        $legacyUrl = route('file-manager.serve', [
            'disk' => 'public',
            'path' => base64_encode($result->filePath),
        ]);

        $response = $this->get($this->toRelativeUri($legacyUrl));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }

    // ── Signed URLs with encrypted path ──────────────────────────────

    #[Test]
    public function signed_url_works_with_encrypted_path(): void
    {
        $this->app['config']->set('file-manager.url.secret', 'test-secret-key');

        $file = UploadedFile::fake()->image('photo.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        $signedUrl = FileUrl::getSignedUrl($result->filePath, now()->addMinutes(5));
        $response = $this->get($this->toRelativeUri($signedUrl));

        $response->assertOk();
    }

    #[Test]
    public function expired_signed_url_is_rejected_with_encrypted_path(): void
    {
        $this->app['config']->set('file-manager.url.secret', 'test-secret-key');

        $file = UploadedFile::fake()->image('photo.png', 10, 10);
        $result = FileManager::save($file, 'testing');

        $expiredUrl = FileUrl::getSignedUrl($result->filePath, now()->subMinute());
        $response = $this->get($this->toRelativeUri($expiredUrl));

        $response->assertForbidden();
    }

    // ── Invalid encoded path ─────────────────────────────────────────

    #[Test]
    public function invalid_encoded_path_returns_404(): void
    {
        $response = $this->get('/file/public/invalid!!!encoded@@@path');

        $response->assertNotFound();
    }

    #[Test]
    public function mising_file_path_returns_404(): void
    {
        $url = FileUrl::getUrl('nonexistent/file.txt');
        $response = $this->get($this->toRelativeUri($url));

        $response->assertNotFound();
    }

    // ── Transition: enabling secret mid-way ──────────────────────────

    #[Test]
    public function decode_accepts_both_new_encrypted_and_legacy_base64(): void
    {
        $this->app['config']->set('file-manager.url.secret', 'test-secret-key');

        // Get the generator and test decodePath directly
        $generator = app(\M2code\FileManager\Domain\Contracts\FileUrlGenerator::class);

        // New encrypted format
        $newUrl = $generator->getUrl($this->testPath);
        $parsedNew = parse_url($newUrl, PHP_URL_PATH);
        $encodedNew = basename($parsedNew);
        $this->assertSame($this->testPath, $generator->decodePath($encodedNew));

        // Legacy base64 format
        $legacyEncoded = base64_encode($this->testPath);
        $this->assertSame($this->testPath, $generator->decodePath($legacyEncoded));
    }

    protected function toRelativeUri(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "$path?$query" : $path;
    }
}
