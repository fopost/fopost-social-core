<?php

declare(strict_types=1);

namespace Synglify\Core\Events\Contracts;

/**
 * Contract for event dispatching.
 *
 * Framework packages implement this with their own event systems:
 * - Laravel: Laravel's event dispatcher
 * - WordPress: do_action / apply_filters
 * - Node.js: EventEmitter
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event.
     *
     * @param object $event The event object to dispatch.
     */
    public function dispatch(object $event): void;
}
