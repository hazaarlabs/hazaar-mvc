<?php

namespace Hazaar\Events;

trait Dispatchable
{
    /**
     * Dispatch the event.
     */
    public static function dispatch(mixed ...$args): object
    {
        $event = new self(...$args);
        EventDispatcher::getInstance()->dispatch($event);

        return $event;
    }
}
