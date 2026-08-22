<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Content\Media;
use Fopost\Social\Content\MediaCollection;
use Fopost\Social\Content\Post;

class PostTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $post = new Post(title: 'Title', body: 'Body');

        $this->assertSame('Title', $post->title);
        $this->assertSame('Body', $post->body);
        $this->assertNull($post->url);
        $this->assertNull($post->excerpt);
        $this->assertNull($post->media);
        $this->assertSame([], $post->tags);
        $this->assertSame([], $post->metadata);
    }

    public function testHasMediaReturnsFalseWhenNoMedia(): void
    {
        $post = new Post(title: 'T', body: 'B');
        $this->assertFalse($post->hasMedia());
    }

    public function testHasMediaReturnsFalseForEmptyCollection(): void
    {
        $post = new Post(title: 'T', body: 'B', media: new MediaCollection([]));
        $this->assertFalse($post->hasMedia());
    }

    public function testHasMediaReturnsTrueWithMedia(): void
    {
        $media = new MediaCollection([new Media('/img.jpg', 'image/jpeg')]);
        $post = new Post(title: 'T', body: 'B', media: $media);
        $this->assertTrue($post->hasMedia());
    }

    public function testHasUrlReturnsTrueForValidUrl(): void
    {
        $post = new Post(title: 'T', body: 'B', url: 'https://example.com');
        $this->assertTrue($post->hasUrl());
    }

    public function testHasUrlReturnsFalseForNull(): void
    {
        $post = new Post(title: 'T', body: 'B', url: null);
        $this->assertFalse($post->hasUrl());
    }

    public function testHasUrlReturnsFalseForEmptyString(): void
    {
        $post = new Post(title: 'T', body: 'B', url: '');
        $this->assertFalse($post->hasUrl());
    }

    public function testGetMetaReturnsValueOrDefault(): void
    {
        $post = new Post(title: 'T', body: 'B', metadata: ['author' => 'Ali']);

        $this->assertSame('Ali', $post->getMeta('author'));
        $this->assertNull($post->getMeta('missing'));
        $this->assertSame('fallback', $post->getMeta('missing', 'fallback'));
    }
}
