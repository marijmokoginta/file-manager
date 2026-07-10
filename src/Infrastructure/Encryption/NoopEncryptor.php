<?php

namespace M2code\FileManager\Infrastructure\Encryption;

use M2code\FileManager\Domain\Contracts\ContentEncryptor;

class NoopEncryptor implements ContentEncryptor
{
    public function encrypt(string $data): string
    {
        return $data;
    }

    public function decrypt(string $data): string
    {
        return $data;
    }
}
