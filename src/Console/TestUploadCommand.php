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
        $dummyContent = 'Hello M2code';
        $path = 'test/test_' . time() . '.txt';

        $result = FileManager::save($dummyContent, 'test');

        $this->info("Saved at: {$result->filePath}");
    }
}