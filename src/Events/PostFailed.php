<?php

declare(strict_types=1);

namespace Owlstack\Core\Events;

use Owlstack\Core\Content\Post;
use Owlstack\Core\Publishing\PublishResult;

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
