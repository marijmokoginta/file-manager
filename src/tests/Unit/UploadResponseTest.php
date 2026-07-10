<?php

namespace M2code\FileManager\tests\Unit;

use M2code\FileManager\DTO\UploadResponse;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UploadResponseTest extends TestCase
{
    #[Test]
    public function it_serializes_to_json_with_snake_case_keys(): void
    {
        $response = new UploadResponse(
            type: 'image',
            tmpPath: 'tmp/uploads/uuid/original.png',
            tmpFolder: 'tmp/uploads/uuid',
            originalName: 'photo.png',
            size: 204800,
            mimeType: 'image/png',
            extra: [
                'blurhash' => 'L6Df8^_4D%M{%MD%M{D%',
                'optimized_path' => 'tmp/uploads/uuid/optimized.avif',
            ],
        );

        $json = json_encode($response);
        $this->assertNotFalse($json);

        $data = json_decode($json, true);

        $this->assertEquals('image', $data['type']);
        $this->assertEquals('tmp/uploads/uuid/original.png', $data['tmp_path']);
        $this->assertEquals('tmp/uploads/uuid', $data['tmp_folder']);
        $this->assertEquals('photo.png', $data['original_name']);
        $this->assertEquals(204800, $data['size']);
        $this->assertEquals('image/png', $data['mime_type']);
        $this->assertIsArray($data['extra']);
        $this->assertEquals('L6Df8^_4D%M{%MD%M{D%', $data['extra']['blurhash']);
        $this->assertEquals('tmp/uploads/uuid/optimized.avif', $data['extra']['optimized_path']);
    }

    #[Test]
    public function extra_is_always_an_object_in_json(): void
    {
        $response = new UploadResponse(
            type: 'svg',
            tmpPath: 'tmp/uploads/uuid/icon.svg',
            tmpFolder: 'tmp/uploads/uuid',
            originalName: null,
            size: 512,
            mimeType: 'image/svg+xml',
            extra: [],
        );

        $json = json_encode($response);
        $data = json_decode($json, true);

        $this->assertEquals([], $data['extra']);
    }

    #[Test]
    public function original_name_can_be_null(): void
    {
        $response = new UploadResponse(
            type: 'document',
            tmpPath: 'tmp/uploads/uuid/doc.pdf',
            tmpFolder: 'tmp/uploads/uuid',
            originalName: null,
            size: 1024,
            mimeType: 'application/pdf',
        );

        $json = json_encode($response);
        $data = json_decode($json, true);

        $this->assertNull($data['original_name']);
    }

    #[Test]
    public function it_handles_image_with_all_extra_fields(): void
    {
        $response = new UploadResponse(
            type: 'image',
            tmpPath: 'tmp/uploads/uuid/original.png',
            tmpFolder: 'tmp/uploads/uuid',
            originalName: 'photo.png',
            size: 204800,
            mimeType: 'image/png',
            extra: [
                'blurhash' => 'L6Df8^_4D%M{%MD%M{D%',
                'optimized_path' => 'tmp/uploads/uuid/optimized.avif',
                'watermark_path' => 'tmp/uploads/uuid/watermark.jpg',
                'low_quality_path' => 'tmp/uploads/uuid/low_quality.jpg',
            ],
        );

        $data = json_decode(json_encode($response), true);

        $this->assertCount(4, $data['extra']);
        $this->assertArrayHasKey('blurhash', $data['extra']);
        $this->assertArrayHasKey('optimized_path', $data['extra']);
        $this->assertArrayHasKey('watermark_path', $data['extra']);
        $this->assertArrayHasKey('low_quality_path', $data['extra']);
    }

    #[Test]
    public function it_handles_document_type_with_no_extra(): void
    {
        $response = new UploadResponse(
            type: 'document',
            tmpPath: 'tmp/uploads/uuid/doc.pdf',
            tmpFolder: 'tmp/uploads/uuid',
            originalName: 'report.pdf',
            size: 512000,
            mimeType: 'application/pdf',
        );

        $data = json_decode(json_encode($response), true);

        $this->assertEquals('document', $data['type']);
        $this->assertEmpty($data['extra']);
    }
}
