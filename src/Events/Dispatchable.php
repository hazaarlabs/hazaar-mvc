<?php

namespace Hazaar\Events;

trait Dispatchable
{
    /**
     * Dispatch the event.
     */
    public static function dispatch(mixed ...$args): void
    {
        $event = new self(...$args);
        EventDispatcher::getInstance()->dispatch($event);
    }
}
