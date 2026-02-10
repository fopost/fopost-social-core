<?php

declare(strict_types=1);

namespace Synglify\Core\Events;

use Synglify\Core\Content\Post;
use Synglify\Core\Publishing\PublishResult;

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
