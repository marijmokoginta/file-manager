<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileMover;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class FileMoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp');
        Storage::fake('public');
    }

    #[Test]
    public function it_moves_file_from_tmp_to_permanent_disk(): void
    {
        // Arrange: create a file in tmp
        $tmpFile = UploadedFile::fake()->image('photo.png', 100, 100);
        $tmpPath = 'tmp/uploads/test/photo.png';
        Storage::disk('tmp')->put($tmpPath, file_get_contents($tmpFile->getRealPath()));

        Storage::disk('tmp')->assertExists($tmpPath);
        Storage::disk('public')->assertMissing('photos/photo.png');

        // Act
        $newPath = FileMover::move($tmpPath, 'photos');

        // Assert
        $this->assertEquals('photos/photo.png', $newPath);
        Storage::disk('tmp')->assertMissing($tmpPath);
        Storage::disk('public')->assertExists('photos/photo.png');
    }

    #[Test]
    public function it_moves_to_specific_disk(): void
    {
        config()->set('filesystems.disks.custom', [
            'driver' => 'local',
            'root' => __DIR__ . '/../storage/custom',
        ]);

        $tmpFile = UploadedFile::fake()->image('photo.png', 100, 100);
        $tmpPath = 'tmp/uploads/test/photo.png';
        Storage::disk('tmp')->put($tmpPath, file_get_contents($tmpFile->getRealPath()));

        $newPath = FileMover::move($tmpPath, 'photos', 'custom');

        $this->assertStringStartsWith('photos/', $newPath);
    }

    #[Test]
    public function it_throws_exception_when_source_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Source file not found/');

        FileMover::move('tmp/uploads/nonexistent/file.png', 'photos');
    }

    #[Test]
    public function it_moves_all_files_in_folder(): void
    {
        $tmpFile1 = UploadedFile::fake()->image('photo1.png', 100, 100);
        $tmpFile2 = UploadedFile::fake()->image('photo2.png', 100, 100);

        Storage::disk('tmp')->put('tmp/uploads/uuid/photo1.png', file_get_contents($tmpFile1->getRealPath()));
        Storage::disk('tmp')->put('tmp/uploads/uuid/photo2.png', file_get_contents($tmpFile2->getRealPath()));

        Storage::disk('tmp')->assertExists('tmp/uploads/uuid/photo1.png');
        Storage::disk('tmp')->assertExists('tmp/uploads/uuid/photo2.png');

        $results = FileMover::moveAll('tmp/uploads/uuid', 'avatars');

        $this->assertCount(2, $results);

        Storage::disk('tmp')->assertMissing('tmp/uploads/uuid/photo1.png');
        Storage::disk('tmp')->assertMissing('tmp/uploads/uuid/photo2.png');
        Storage::disk('public')->assertExists('avatars/photo1.png');
        Storage::disk('public')->assertExists('avatars/photo2.png');
    }

    #[Test]
    public function it_deletes_source_after_successful_move(): void
    {
        $tmpFile = UploadedFile::fake()->image('photo.png', 100, 100);
        $tmpPath = 'tmp/uploads/test/photo.png';
        Storage::disk('tmp')->put($tmpPath, file_get_contents($tmpFile->getRealPath()));

        FileMover::move($tmpPath, 'photos');

        Storage::disk('tmp')->assertMissing($tmpPath);
    }

    #[Test]
    public function it_preserves_filename_during_move(): void
    {
        $tmpFile = UploadedFile::fake()->image('my-avatar.png', 100, 100);
        $tmpPath = 'tmp/uploads/test/my-avatar.png';
        Storage::disk('tmp')->put($tmpPath, file_get_contents($tmpFile->getRealPath()));

        $newPath = FileMover::move($tmpPath, 'profiles');

        $this->assertEquals('profiles/my-avatar.png', $newPath);
    }
}
