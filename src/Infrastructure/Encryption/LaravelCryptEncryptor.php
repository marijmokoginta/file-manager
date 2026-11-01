<?php

namespace M2code\FileManager\Infrastructure\Encryption;

use Illuminate\Support\Facades\Crypt;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;

class LaravelCryptEncryptor implements ContentEncryptor
{
    public function encrypt(string $data): string
    {
        return Crypt::encryptString($data);
    }

    public function decrypt(string $data): string
    {
        return Crypt::decryptString($data);
    }
}
