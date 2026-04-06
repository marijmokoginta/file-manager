<?php

namespace M2code\FileManager\Console;

use Illuminate\Console\Command;
use M2code\FileManager\Facades\FileManager;

class TestUploadCommand extends Command
{
    protected $signature = 'file-manager:test-upload';
    protected $description = 'Test uploading dummy file using FileManager';

    public function handle()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20"/></svg>';

        $result = FileManager::save($svg, 'test');

        $this->info("Saved at: {$result->filePath}");
    }
}
