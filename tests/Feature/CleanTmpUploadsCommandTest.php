<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CleanTmpUploadsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp');
        Storage::fake('public');

        // Set a very short lifetime so files are expired immediately
        config()->set('file-manager.tmp.lifetime', 1);
    }

    #[Test]
    public function it_deletes_expired_tmp_directories(): void
    {
        // Arrange: create files with old modification time
        $file = UploadedFile::fake()->image('photo.png', 100, 100);
        $content = file_get_contents($file->getRealPath());

        Storage::disk('tmp')->put('tmp/uploads/expired-uuid/photo.png', $content);
        Storage::disk('tmp')->put('tmp/uploads/expired-uuid/variant.png', $content);

        Storage::disk('tmp')->assertExists('tmp/uploads/expired-uuid/photo.png');
        Storage::disk('tmp')->assertExists('tmp/uploads/expired-uuid/variant.png');

        // Simulate old timestamp by sleeping past the lifetime
        sleep(2);

        // Act
        $this->artisan('file-manager:clean-tmp')
            ->assertSuccessful();

        // Assert
        Storage::disk('tmp')->assertMissing('tmp/uploads/expired-uuid/photo.png');
        Storage::disk('tmp')->assertMissing('tmp/uploads/expired-uuid/variant.png');
    }

    #[Test]
    public function it_does_not_delete_recent_files(): void
    {
        config()->set('file-manager.tmp.lifetime', 3600); // 1 hour

        $file = UploadedFile::fake()->image('photo.png', 100, 100);
        $content = file_get_contents($file->getRealPath());

        Storage::disk('tmp')->put('tmp/uploads/recent-uuid/photo.png', $content);

        Storage::disk('tmp')->assertExists('tmp/uploads/recent-uuid/photo.png');

        // Act — lifetime is 3600s, file just created, won't be deleted
        $this->artisan('file-manager:clean-tmp')
            ->assertSuccessful();

        // Assert — file still exists
        Storage::disk('tmp')->assertExists('tmp/uploads/recent-uuid/photo.png');
    }

    #[Test]
    public function it_reports_deleted_count(): void
    {
        config()->set('file-manager.tmp.lifetime', 1);

        $file = UploadedFile::fake()->image('photo.png', 100, 100);
        $content = file_get_contents($file->getRealPath());

        Storage::disk('tmp')->put('tmp/uploads/old-uuid/photo.png', $content);

        sleep(2);

        $this->artisan('file-manager:clean-tmp')
            ->assertSuccessful()
            ->expectsOutputToContain('Cleaned up 1 expired temporary upload directories.');
    }

    #[Test]
    public function it_handles_empty_tmp_directory(): void
    {
        $this->artisan('file-manager:clean-tmp')
            ->assertSuccessful()
            ->expectsOutputToContain('Cleaned up 0 expired temporary upload directories.');
    }
}
