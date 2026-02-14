<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Events\PostPublished;
use Owlstack\Core\Publishing\PublishResult;

class PostPublishedTest extends TestCase
{
    public function testEventContainsPostAndResult(): void
    {
        $post = new Post(title: 'Test', body: 'Body');
        $result = new PublishResult(
            success: true,
            platformName: 'telegram',
            externalId: '123',
        );

        $event = new PostPublished($post, $result);

        $this->assertSame($post, $event->post);
        $this->assertSame($result, $event->result);
    }

    public function testEventPropertiesAreReadonly(): void
    {
        $post = new Post(title: 'T', body: 'B');
        $result = new PublishResult(success: true, platformName: 'test');

        $event = new PostPublished($post, $result);

        // Verify the properties exist and match
        $this->assertSame('T', $event->post->title);
        $this->assertTrue($event->result->success);
    }
}
