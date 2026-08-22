<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Content\Media;
use Fopost\Social\Content\MediaCollection;

class MediaCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $col = new MediaCollection();

        $this->assertTrue($col->isEmpty());
        $this->assertSame(0, $col->count());
        $this->assertNull($col->first());
        $this->assertSame([], $col->all());
    }

    public function testAddReturnsNewInstance(): void
    {
        $col = new MediaCollection();
        $media = new Media('/img.jpg', 'image/jpeg');

        $col2 = $col->add($media);

        $this->assertTrue($col->isEmpty());
        $this->assertSame(1, $col2->count());
    }

    public function testFirst(): void
    {
        $m1 = new Media('/a.jpg', 'image/jpeg');
        $m2 = new Media('/b.jpg', 'image/jpeg');

        $col = new MediaCollection([$m1, $m2]);

        $this->assertSame($m1, $col->first());
    }

    public function testImagesFilter(): void
    {
        $img = new Media('/a.jpg', 'image/jpeg');
        $vid = new Media('/b.mp4', 'video/mp4');

        $col = new MediaCollection([$img, $vid]);

        $images = $col->images();
        $this->assertSame(1, $images->count());
        $this->assertTrue($images->first()->isImage());
    }

    public function testVideosFilter(): void
    {
        $img = new Media('/a.jpg', 'image/jpeg');
        $vid = new Media('/b.mp4', 'video/mp4');

        $col = new MediaCollection([$img, $vid]);

        $videos = $col->videos();
        $this->assertSame(1, $videos->count());
        $this->assertTrue($videos->first()->isVideo());
    }

    public function testIsIterable(): void
    {
        $m1 = new Media('/a.jpg', 'image/jpeg');
        $col = new MediaCollection([$m1]);

        $items = [];
        foreach ($col as $item) {
            $items[] = $item;
        }

        $this->assertCount(1, $items);
        $this->assertSame($m1, $items[0]);
    }
}
