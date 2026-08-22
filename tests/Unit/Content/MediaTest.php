<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Content\Media;

class MediaTest extends TestCase
{
    public function testIsImage(): void
    {
        $media = new Media('/img.jpg', 'image/jpeg');
        $this->assertTrue($media->isImage());
        $this->assertFalse($media->isVideo());
        $this->assertFalse($media->isAudio());
        $this->assertFalse($media->isDocument());
    }

    public function testIsVideo(): void
    {
        $media = new Media('/vid.mp4', 'video/mp4');
        $this->assertTrue($media->isVideo());
        $this->assertFalse($media->isImage());
    }

    public function testIsAudio(): void
    {
        $media = new Media('/audio.mp3', 'audio/mpeg');
        $this->assertTrue($media->isAudio());
        $this->assertFalse($media->isImage());
    }

    public function testIsDocument(): void
    {
        $media = new Media('/doc.pdf', 'application/pdf');
        $this->assertTrue($media->isDocument());
        $this->assertFalse($media->isImage());
        $this->assertFalse($media->isVideo());
        $this->assertFalse($media->isAudio());
    }

    public function testOptionalProperties(): void
    {
        $media = new Media(
            path: '/img.jpg',
            mimeType: 'image/jpeg',
            altText: 'A photo',
            width: 1920,
            height: 1080,
            fileSize: 102400,
            duration: null,
        );

        $this->assertSame('A photo', $media->altText);
        $this->assertSame(1920, $media->width);
        $this->assertSame(1080, $media->height);
        $this->assertSame(102400, $media->fileSize);
        $this->assertNull($media->duration);
    }
}
