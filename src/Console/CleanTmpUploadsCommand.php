<?php

namespace M2code\FileManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class CleanTmpUploadsCommand extends Command
{
    protected $signature = 'file-manager:clean-tmp';

    protected $description = 'Delete expired temporary upload files';

    public function handle(): int
    {
        $disk = Config::get('file-manager.tmp.disk', 'local');
        $prefix = Config::get('file-manager.tmp.prefix', 'tmp/uploads');
        $lifetime = (int) Config::get('file-manager.tmp.lifetime', 86400);

        $storage = Storage::disk($disk);
        $expiredBefore = now()->subSeconds($lifetime);

        $directories = $storage->directories($prefix);

        $deletedCount = 0;

        foreach ($directories as $directory) {
            $lastModified = $storage->lastModified($directory);

            if ($lastModified && $lastModified < $expiredBefore->getTimestamp()) {
                $files = $storage->allFiles($directory);

                foreach ($files as $file) {
                    $storage->delete($file);
                }

                $storage->deleteDirectory($directory);

                $deletedCount++;
            }
        }

        $this->info("Cleaned up {$deletedCount} expired temporary upload directories.");

        return self::SUCCESS;
    }
}
