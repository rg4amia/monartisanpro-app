<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Event;

/**
 * Domain event dispatcher for firing domain events
 */
class DomainEventDispatcher
{
    /**
     * Dispatch a domain event
     */
    public static function dispatch(object $event): void
    {
        Event::dispatch($event);
    }

    /**
     * Dispatch multiple domain events
     */
    public static function dispatchMultiple(array $events): void
    {
        foreach ($events as $event) {
            self::dispatch($event);
        }
    }
}
