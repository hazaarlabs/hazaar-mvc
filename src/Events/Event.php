<?php

namespace Hazaar\Events;

/**
 * Class Event.
 *
 * Provides static methods to interact with the event dispatching system.
 * This acts as a facade for the EventDispatcher singleton instance.
 */
class Event
{
    /**
     * Register an event listener.
     *
     * Adds a listener object to the EventDispatcher. The listener object should
     * have a `handle` method that accepts the event object as its first parameter.
     *
     * @param object $listener the listener object to register
     */
    public static function listen(object $listener): void
    {
        EventDispatcher::getInstance()->addListener($listener);
    }

    /**
     * Get all listeners registered for a specific event.
     *
     * @param string $event the fully qualified class name of the event
     *
     * @return array<object> an array of listener objects registered for the event
     */
    public static function getListeners(string $event): array
    {
        return EventDispatcher::getInstance()->getListeners($event);
    }

    /**
     * Clear all registered listeners from the EventDispatcher.
     */
    public static function clearListeners(): void
    {
        EventDispatcher::getInstance()->clearListeners();
    }

    /**
     * Dispatch all queued events.
     *
     * Processes and executes the handle method for all listeners associated
     * with events that were marked as Queuable and deferred.
     */
    public static function dispatchQueue(?string $eventName = null): void
    {
        EventDispatcher::getInstance()->dispatchQueue($eventName);
    }
}
