<?php

declare(strict_types=1);

namespace Synglify\Core\Events;

use Synglify\Core\Content\Post;
use Synglify\Core\Publishing\PublishResult;

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
