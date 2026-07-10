<?php

namespace M2code\FileManager\Infrastructure\UrlGenerator;

use DateTimeInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;

class LocalFileUrlGenerator implements FileUrlGenerator
{
    protected const ROUTE_NAME = 'file-manager.serve';

    protected string $disk;

    protected ?string $secret;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 'public';
        $this->secret = Config::get('file-manager.url.secret');
    }

    public function getUrl(string $path): string
    {
        return route(self::ROUTE_NAME, [
            'disk' => $this->disk,
            'path' => $this->encodePath($path),
        ]);
    }

    public function getSignedUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return URL::temporarySignedRoute(self::ROUTE_NAME, $expiresAt, [
            'disk' => $this->disk,
            'path' => $this->encodePath($path),
        ]);
    }

    public function decodePath(string $encoded): ?string
    {
        // Try new encrypted format first
        if ($this->secret) {
            $data = base64_decode(strtr($encoded, '-_', '+/'), true);

            if ($data !== false && strlen($data) > 16) {
                $iv = substr($data, 0, 16);
                $ciphertext = substr($data, 16);

                $decrypted = openssl_decrypt(
                    $ciphertext,
                    'aes-256-cbc',
                    $this->deriveKey(),
                    OPENSSL_RAW_DATA,
                    $iv
                );

                if ($decrypted !== false && $decrypted !== '') {
                    return $decrypted;
                }
            }
        }

        // Fallback: legacy base64 encoding
        $decoded = base64_decode($encoded, true);

        return ($decoded !== false && $decoded !== '') ? $decoded : null;
    }

    protected function encodePath(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->secret) {
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt(
                $path,
                'aes-256-cbc',
                $this->deriveKey(),
                OPENSSL_RAW_DATA,
                $iv
            );

            // URL-safe base64
            return rtrim(strtr(base64_encode($iv.$encrypted), '+/', '-_'), '=');
        }

        // Legacy: plain base64
        return base64_encode($path);
    }

    /**
     * Derive a 256-bit key from the configured secret.
     */
    protected function deriveKey(): string
    {
        return substr(hash('sha256', $this->secret, true), 0, 32);
    }
}
