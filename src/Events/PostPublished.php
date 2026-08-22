<?php

declare(strict_types=1);

namespace Fopost\Social\Events;

use Fopost\Social\Content\Post;
use Fopost\Social\Publishing\PublishResult;

/**
 * Event fired after content is successfully published to a platform.
 */
class PostPublished
{
    public function __construct(
        public readonly Post $post,
        public readonly PublishResult $result,
    ) {
    }
}
