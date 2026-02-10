<?php

declare(strict_types=1);

namespace Synglify\Core\Publishing;

use Synglify\Core\Content\Post;
use Synglify\Core\Events\Contracts\EventDispatcherInterface;
use Synglify\Core\Events\PostPublished;
use Synglify\Core\Events\PostFailed;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\PlatformRegistry;

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
                success: $response->success(),
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
