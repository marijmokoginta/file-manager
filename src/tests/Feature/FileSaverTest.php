<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\tests\TestCase;
use function PHPUnit\Framework\assertNotNull;

class FileSaverTest extends TestCase
{
    /**
     * @test
     */
    public function test_it_can_save_a_file_using_local_driver()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('dummy.png');
        $result = FileManager::save($file, 'testing');

        assertNotNull($result->filePath);

        Storage::disk('public')->assertExists($result->filePath);
    }

    public function test_it_cannot_save_file_with_no_available_handler()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('dummy', 2, 'txt');

        $this->assertThrows(
            function () use ($file) {
                FileManager::save($file, 'testing');
            },
            \RuntimeException::class,
            'No handler available for this file type.'
        );
    }
}