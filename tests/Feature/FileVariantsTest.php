<?php

namespace M2code\FileManager\tests\Feature;

use M2code\FileManager\Domain\ValueObjects\FileVariant;
use M2code\FileManager\Domain\ValueObjects\FileVariants;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileVariantsTest extends TestCase
{
    #[Test]
    public function test_it_can_add_and_retrieve_variants(): void
    {
        $variants = new FileVariants;
        $variants->add(new FileVariant('original', 'uploads/original.jpg'));
        $variants->add(new FileVariant('low_quality', 'uploads/low.jpg'));

        self::assertSame('uploads/original.jpg', $variants->get('original')?->path);
        self::assertSame('uploads/low.jpg', $variants->get('low_quality')?->path);
        self::assertNull($variants->get('optimized'));
    }

    #[Test]
    public function test_it_returns_all_variants_and_paths(): void
    {
        $variants = new FileVariants;
        $variants->add(new FileVariant('original', 'uploads/original.jpg'));
        $variants->add(new FileVariant('low_quality', 'uploads/low.jpg'));
        $variants->add(new FileVariant('watermark', 'uploads/watermark.jpg'));

        self::assertCount(3, $variants->all());
        self::assertSame([
            'uploads/original.jpg',
            'uploads/low.jpg',
            'uploads/watermark.jpg',
        ], $variants->paths());
    }
}
