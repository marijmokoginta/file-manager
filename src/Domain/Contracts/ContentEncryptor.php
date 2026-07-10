<?php

namespace M2code\FileManager\Domain\Contracts;

interface ContentEncryptor
{
    public function encrypt(string $data): string;

    public function decrypt(string $data): string;
}
