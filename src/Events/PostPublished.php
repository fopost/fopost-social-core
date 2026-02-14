<?php

declare(strict_types=1);

namespace Owlstack\Core\Events;

use Owlstack\Core\Content\Post;
use Owlstack\Core\Publishing\PublishResult;

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
