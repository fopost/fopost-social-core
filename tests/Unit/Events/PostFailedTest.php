<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Events\PostFailed;
use Owlstack\Core\Publishing\PublishResult;

class PostFailedTest extends TestCase
{
    public function testEventContainsPostAndResult(): void
    {
        $post = new Post(title: 'Test', body: 'Body');
        $result = new PublishResult(
            success: false,
            platformName: 'twitter',
            error: 'Rate limit',
        );

        $event = new PostFailed($post, $result);

        $this->assertSame($post, $event->post);
        $this->assertSame($result, $event->result);
    }

    public function testEventCarriesFailureInfo(): void
    {
        $post = new Post(title: 'T', body: 'B');
        $result = new PublishResult(
            success: false,
            platformName: 'facebook',
            error: 'Connection refused',
        );

        $event = new PostFailed($post, $result);

        $this->assertFalse($event->result->success);
        $this->assertTrue($event->result->failed());
        $this->assertSame('Connection refused', $event->result->error);
        $this->assertSame('facebook', $event->result->platformName);
    }
}
