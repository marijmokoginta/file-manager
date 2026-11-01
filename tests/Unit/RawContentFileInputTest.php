<?php

namespace M2code\FileManager\tests\Unit;

use M2code\FileManager\Application\FileInput\RawContentFileInput;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RawContentFileInputTest extends TestCase
{
    #[Test]
    public function it_returns_provided_content(): void
    {
        $input = new RawContentFileInput('binary-data', 'png');

        $this->assertSame('binary-data', $input->getContent());
    }

    #[Test]
    public function it_detects_png_mime(): void
    {
        $input = new RawContentFileInput('data', 'png');

        $this->assertSame('image/png', $input->getMimeType());
        $this->assertSame('png', $input->getExtension());
    }

    #[Test]
    public function it_detects_jpeg_mime(): void
    {
        $input = new RawContentFileInput('data', 'jpg');

        $this->assertSame('image/jpeg', $input->getMimeType());
        $this->assertSame('jpg', $input->getExtension());
    }

    #[Test]
    public function it_detects_svg_mime(): void
    {
        $input = new RawContentFileInput('<svg></svg>', 'svg');

        $this->assertSame('image/svg+xml', $input->getMimeType());
        $this->assertSame('svg', $input->getExtension());
    }

    #[Test]
    public function it_detects_pdf_mime(): void
    {
        $input = new RawContentFileInput('%PDF-1.4', 'pdf');

        $this->assertSame('application/pdf', $input->getMimeType());
        $this->assertSame('pdf', $input->getExtension());
    }

    #[Test]
    public function it_uses_custom_mime_when_provided(): void
    {
        $input = new RawContentFileInput('data', 'bin', 'application/octet-stream');

        $this->assertSame('application/octet-stream', $input->getMimeType());
    }

    #[Test]
    public function it_returns_client_original_name(): void
    {
        $input = new RawContentFileInput('data', 'png', null, 'photo.png');

        $this->assertSame('photo.png', $input->getClientOriginalName());
    }

    #[Test]
    public function it_returns_null_client_original_name_when_not_provided(): void
    {
        $input = new RawContentFileInput('data', 'png');

        $this->assertNull($input->getClientOriginalName());
    }

    #[Test]
    public function it_strips_leading_dot_from_extension(): void
    {
        $input = new RawContentFileInput('data', '.png');

        $this->assertSame('png', $input->getExtension());
        $this->assertSame('image/png', $input->getMimeType());
    }

    #[Test]
    public function it_defaults_to_octet_stream_for_unknown_extensions(): void
    {
        $input = new RawContentFileInput('data', 'unknown');

        $this->assertSame('application/octet-stream', $input->getMimeType());
    }
}
