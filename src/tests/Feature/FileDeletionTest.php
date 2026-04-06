<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileDeletionTest extends TestCase
{
    #[Test]
    public function test_it_can_delete_single_file(): void
    {
        Storage::fake('public');

        $saved = FileManager::save(UploadedFile::fake()->image('single.png'), 'testing');
        Storage::disk('public')->assertExists($saved->filePath);

        self::assertTrue(FileManager::delete($saved->filePath));
        Storage::disk('public')->assertMissing($saved->filePath);

        // Idempotent: deleting the same path again remains safe.
        self::assertTrue(FileManager::delete($saved->filePath));
    }

    #[Test]
    public function test_it_can_delete_many_files_with_result_map(): void
    {
        Storage::fake('public');

        $first = FileManager::save(UploadedFile::fake()->image('first.png'), 'testing');
        $second = FileManager::save(UploadedFile::fake()->image('second.png'), 'testing');
        $missing = 'testing/missing-file.png';

        $results = FileManager::deleteMany([
            $first->filePath,
            $second->filePath,
            $missing,
        ]);

        self::assertSame([
            $first->filePath => true,
            $second->filePath => true,
            $missing => true,
        ], $results);

        Storage::disk('public')->assertMissing($first->filePath);
        Storage::disk('public')->assertMissing($second->filePath);
    }
}
