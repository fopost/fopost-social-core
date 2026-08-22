<?php

declare(strict_types=1);

namespace Fopost\Social\Delivery;

/**
 * Represents the lifecycle status of a content delivery.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
}
