<?php

namespace Hazaar\Events;

/**
 * Trait Dispatchable
 *
 * Provides a static `dispatch` method to classes that use this trait.
 * This allows event classes to be easily dispatched without needing to
 * manually instantiate them and pass them to the EventDispatcher.
 */
trait Dispatchable
{
    /**
     * Dispatch the event.
     *
     * Creates a new instance of the class using this trait with the provided arguments,
     * and then dispatches it through the EventDispatcher.
     *
     * @param mixed ...$args Arguments to pass to the event class constructor.
     *
     * @return object The newly created and dispatched event object.
     */
    public static function dispatch(mixed ...$args): object
    {
        $event = new self(...$args);
        EventDispatcher::getInstance()->dispatch($event);

        return $event;
    }
}
