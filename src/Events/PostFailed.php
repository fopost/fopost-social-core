<?php

declare(strict_types=1);

namespace Fopost\Social\Events;

use Fopost\Social\Content\Post;
use Fopost\Social\Publishing\PublishResult;

/**
 * Event fired after a publish operation fails.
 */
class PostFailed
{
    public function __construct(
        public readonly Post $post,
        public readonly PublishResult $result,
    ) {
    }
}
