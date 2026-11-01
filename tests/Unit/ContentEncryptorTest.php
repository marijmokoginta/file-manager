<?php

namespace M2code\FileManager\tests\Unit;

use M2code\FileManager\Infrastructure\Encryption\LaravelCryptEncryptor;
use M2code\FileManager\Infrastructure\Encryption\NoopEncryptor;
use M2code\FileManager\Infrastructure\Encryption\OpenSslEncryptor;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ContentEncryptorTest extends TestCase
{
    private string $plaintext = 'Hello, this is sensitive file content.';

    // ── NoopEncryptor ────────────────────────────────────────────────

    #[Test]
    public function noop_encrypt_returns_original_data(): void
    {
        $encryptor = new NoopEncryptor;

        $result = $encryptor->encrypt($this->plaintext);

        $this->assertSame($this->plaintext, $result);
    }

    #[Test]
    public function noop_decrypt_returns_original_data(): void
    {
        $encryptor = new NoopEncryptor;

        $result = $encryptor->decrypt($this->plaintext);

        $this->assertSame($this->plaintext, $result);
    }

    // ── LaravelCryptEncryptor ────────────────────────────────────────

    #[Test]
    public function laravel_crypt_encrypt_returns_different_string(): void
    {
        $encryptor = new LaravelCryptEncryptor;

        $result = $encryptor->encrypt($this->plaintext);

        $this->assertNotNull($result);
        $this->assertNotSame($this->plaintext, $result);
    }

    #[Test]
    public function laravel_crypt_decrypt_returns_original(): void
    {
        $encryptor = new LaravelCryptEncryptor;

        $encrypted = $encryptor->encrypt($this->plaintext);
        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertSame($this->plaintext, $decrypted);
    }

    #[Test]
    public function laravel_crypt_encrypt_produces_different_output_each_time(): void
    {
        $encryptor = new LaravelCryptEncryptor;

        $result1 = $encryptor->encrypt($this->plaintext);
        $result2 = $encryptor->encrypt($this->plaintext);

        $this->assertNotSame($result1, $result2);
    }

    #[Test]
    public function laravel_crypt_decrypt_invalid_data_throws_exception(): void
    {
        $encryptor = new LaravelCryptEncryptor;

        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);

        $encryptor->decrypt('invalid-encrypted-data');
    }

    // ── OpenSslEncryptor ─────────────────────────────────────────────

    #[Test]
    public function openssl_encrypt_returns_different_string(): void
    {
        $encryptor = new OpenSslEncryptor('test-secret-key-for-openssl-test');

        $result = $encryptor->encrypt($this->plaintext);

        $this->assertNotNull($result);
        $this->assertNotSame($this->plaintext, $result);
    }

    #[Test]
    public function openssl_decrypt_returns_original(): void
    {
        $encryptor = new OpenSslEncryptor('test-secret-key-for-openssl-test');

        $encrypted = $encryptor->encrypt($this->plaintext);
        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertSame($this->plaintext, $decrypted);
    }

    #[Test]
    public function openssl_encrypt_produces_different_output_each_time(): void
    {
        $encryptor = new OpenSslEncryptor('test-secret-key-for-openssl-test');

        $result1 = $encryptor->encrypt($this->plaintext);
        $result2 = $encryptor->encrypt($this->plaintext);

        $this->assertNotSame($result1, $result2);
    }

    #[Test]
    public function openssl_decrypt_invalid_data_returns_original(): void
    {
        $encryptor = new OpenSslEncryptor('test-secret-key-for-openssl-test');

        // OpenSslEncryptor falls back to returning original data on failure
        $result = $encryptor->decrypt('not-encrypted-data');

        $this->assertSame('not-encrypted-data', $result);
    }

    #[Test]
    public function openssl_encrypt_requires_minimum_key_length(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key must be at least 32 characters');

        new OpenSslEncryptor('short');
    }
}
