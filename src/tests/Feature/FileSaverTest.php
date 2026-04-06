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
    public function test_it_can_save_data_uri_svg()
    {
        Storage::fake('public');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
        $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);

        $result = FileManager::save($dataUri, 'testing');

        self::assertNotNull($result->filePath);
        self::assertStringEndsWith('.svg', $result->filePath);
        Storage::disk('public')->assertExists($result->filePath);
    }

    #[Test]
    public function test_it_can_save_raw_svg_string()
    {
        Storage::fake('public');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12"><circle cx="6" cy="6" r="5"/></svg>';
        $result = FileManager::save($svg, 'testing');

        self::assertNotNull($result->filePath);
        self::assertStringEndsWith('.svg', $result->filePath);
        Storage::disk('public')->assertExists($result->filePath);
    }

    #[Test]
    public function test_it_rejects_invalid_base64_data_uri()
    {
        Storage::fake('public');

        $this->assertThrows(
            function () {
                FileManager::save('data:image/png;base64,%%%%', 'testing');
            },
            RuntimeException::class,
            'Failed to decode base64 data URI.'
        );
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
