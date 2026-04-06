<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use function PHPUnit\Framework\assertNotNull;

class FileSaverTest extends TestCase
{
    #[Test]
    public function test_it_can_save_a_file_using_local_driver()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('dummy.png');
        $result = FileManager::save($file, 'testing');

        assertNotNull($result->filePath);

        Storage::disk('public')->assertExists($result->filePath);
    }

    #[Test]
    public function test_it_can_save_data_uri_image()
    {
        Storage::fake('public');

        $dataUri = 'data:image/png;base64,' .
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAukB9sJfDgAAAABJRU5ErkJggg==';

        $result = FileManager::save($dataUri, 'testing');

        self::assertNotNull($result->filePath);
        Storage::disk('public')->assertExists($result->filePath);
    }

    #[Test]
    public function test_it_cannot_save_file_with_no_available_handler()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('dummy', 2, 'txt');

        $this->assertThrows(
            function () use ($file) {
                FileManager::save($file, 'testing');
            },
            RuntimeException::class,
            'No handler available for this file type.'
        );
    }
}
