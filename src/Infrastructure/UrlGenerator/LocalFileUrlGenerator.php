<?php

namespace M2code\FileManager\Infrastructure\UrlGenerator;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;

class LocalFileUrlGenerator implements FileUrlGenerator
{

    protected const ROUTE_NAME = 'file-manager.serve';

    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 'public';
    }

    public function getUrl(string $path): string
    {
        return route(self::ROUTE_NAME, [
            'disk' => $this->disk,
            'path' => $this->getDecodedPath($path)
        ]);
    }

    public function getSignedUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return URL::temporarySignedRoute(self::ROUTE_NAME, $expiresAt, [
            'disk' => $this->disk,
            'path' => $this->getDecodedPath($path)
        ]);
    }

    protected function getDecodedPath(string $path): string
    {
        return base64_encode(ltrim($path, '/'));
    }
}