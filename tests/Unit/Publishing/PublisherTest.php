<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Events\Contracts\EventDispatcherInterface;
use Owlstack\Core\Events\PostFailed;
use Owlstack\Core\Events\PostPublished;
use Owlstack\Core\Exceptions\PlatformException;
use Owlstack\Core\Platforms\Contracts\PlatformInterface;
use Owlstack\Core\Platforms\Contracts\PlatformResponseInterface;
use Owlstack\Core\Platforms\PlatformRegistry;
use Owlstack\Core\Platforms\PlatformResponse;
use Owlstack\Core\Publishing\Publisher;

class PublisherTest extends TestCase
{
    private PlatformRegistry $registry;
    private Post $post;

    protected function setUp(): void
    {
        $this->registry = new PlatformRegistry();
        $this->post = new Post(title: 'Test', body: 'Body');
    }

    public function testPublishSuccessful(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('telegram');
        $platform->method('publish')->willReturn(
            PlatformResponse::success('123', 'https://example.com/123')
        );

        $this->registry->register($platform);
        $publisher = new Publisher($this->registry);

        $result = $publisher->publish($this->post, 'telegram');

        $this->assertTrue($result->success);
        $this->assertSame('123', $result->externalId);
        $this->assertSame('https://example.com/123', $result->externalUrl);
        $this->assertSame('telegram', $result->platformName);
    }

    public function testPublishFailedResponse(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('twitter');
        $platform->method('publish')->willReturn(
            PlatformResponse::failure('API error')
        );

        $this->registry->register($platform);
        $publisher = new Publisher($this->registry);

        $result = $publisher->publish($this->post, 'twitter');

        $this->assertFalse($result->success);
        $this->assertSame('API error', $result->error);
    }

    public function testPublishCatchesException(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('facebook');
        $platform->method('publish')->willThrowException(
            new PlatformException(
                message: 'Connection timeout',
                platformName: 'facebook',
            )
        );

        $this->registry->register($platform);
        $publisher = new Publisher($this->registry);

        $result = $publisher->publish($this->post, 'facebook');

        $this->assertFalse($result->success);
        $this->assertSame('Connection timeout', $result->error);
        $this->assertSame('facebook', $result->platformName);
    }

    public function testPublishDispatchesPostPublishedEvent(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('telegram');
        $platform->method('publish')->willReturn(
            PlatformResponse::success('999')
        );
        $this->registry->register($platform);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PostPublished::class));

        $publisher = new Publisher($this->registry, $dispatcher);
        $publisher->publish($this->post, 'telegram');
    }

    public function testPublishDispatchesPostFailedEventOnFailedResponse(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('twitter');
        $platform->method('publish')->willReturn(
            PlatformResponse::failure('Oops')
        );
        $this->registry->register($platform);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PostFailed::class));

        $publisher = new Publisher($this->registry, $dispatcher);
        $publisher->publish($this->post, 'twitter');
    }

    public function testPublishDispatchesPostFailedEventOnException(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('facebook');
        $platform->method('publish')->willThrowException(
            new \RuntimeException('Boom')
        );
        $this->registry->register($platform);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PostFailed::class));

        $publisher = new Publisher($this->registry, $dispatcher);
        $publisher->publish($this->post, 'facebook');
    }

    public function testPublishPassesOptionsToPlatform(): void
    {
        $options = ['chat_id' => '@mychannel', 'parse_mode' => 'Markdown'];

        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('name')->willReturn('telegram');
        $platform->expects($this->once())
            ->method('publish')
            ->with($this->post, $options)
            ->willReturn(PlatformResponse::success('1'));
        $this->registry->register($platform);

        $publisher = new Publisher($this->registry);
        $publisher->publish($this->post, 'telegram', $options);
    }
}
