<?php

declare(strict_types=1);

namespace Fopost\Social\Publishing;

use Fopost\Social\Content\Post;
use Fopost\Social\Events\Contracts\EventDispatcherInterface;
use Fopost\Social\Events\PostPublished;
use Fopost\Social\Events\PostFailed;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Platforms\PlatformRegistry;

/**
 * The main publishing orchestrator.
 *
 * Accepts a Post and a platform, formats the content using the platform's
 * formatter, publishes it, and returns a result.
 */
class Publisher
{
    public function __construct(
        private readonly PlatformRegistry $platforms,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * Publish a post to a single platform.
     *
     * @param Post   $post         The content to publish.
     * @param string $platformName The platform to publish to.
     * @param array  $options      Platform-specific publishing options.
     */
    public function publish(Post $post, string $platformName, array $options = []): PublishResult
    {
        $platform = $this->platforms->get($platformName);

        try {
            $response = $platform->publish($post, $options);

            $result = new PublishResult(
                success: $response->isSuccess(),
                platformName: $platformName,
                externalId: $response->externalId(),
                externalUrl: $response->externalUrl(),
                error: $response->errorMessage(),
            );

            if ($result->success && $this->eventDispatcher) {
                $this->eventDispatcher->dispatch(new PostPublished($post, $result));
            }

            if (!$result->success && $this->eventDispatcher) {
                $this->eventDispatcher->dispatch(new PostFailed($post, $result));
            }

            return $result;
        } catch (\Throwable $e) {
            $result = new PublishResult(
                success: false,
                platformName: $platformName,
                error: $e->getMessage(),
            );

            if ($this->eventDispatcher) {
                $this->eventDispatcher->dispatch(new PostFailed($post, $result));
            }

            return $result;
        }
    }
}
