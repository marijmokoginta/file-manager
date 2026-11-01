<?php

namespace M2code\FileManager\Infrastructure\Encryption;

use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use RuntimeException;

class OpenSslEncryptor implements ContentEncryptor
{
    private const CIPHER = 'aes-256-cbc';

    private string $key;

    public function __construct(string $key)
    {
        if (strlen($key) < 32) {
            throw new RuntimeException('OpenSslEncryptor key must be at least 32 characters.');
        }

        $this->key = substr(hash('sha256', $key, true), 0, 32);
    }

    public function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $encrypted = openssl_encrypt($data, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('OpenSSL encryption failed: '.openssl_error_string());
        }

        return base64_encode($iv.$encrypted);
    }

    public function decrypt(string $data): string
    {
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return $data;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (strlen($decoded) < $ivLength + 1) {
            return $data;
        }

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : $data;
    }
}
