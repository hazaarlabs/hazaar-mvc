<?php

namespace Hazaar\Events;

class Event
{
    /**
     * Register an event listener.
     */
    public static function listen(string $event, string $listener): void
    {
        EventDispatcher::getInstance()->listen($event, $listener);
    }

    /**
     * @return array<object>
     */
    public static function getListeners(string $event): array
    {
        return EventDispatcher::getInstance()->getListeners($event);
    }

    /**
     * Clear all registered listeners.
     */
    public static function clearListeners(): void
    {
        EventDispatcher::getInstance()->clearListeners();
    }
}
