<?php

use Hazaar\Events\Event;
use Hazaar\Events\EventDispatcher;

/**
 * Dispatches an event with the given name and arguments.
 *
 * @param string $event   the name of the event to dispatch
 * @param mixed  ...$args The arguments to pass to the event listeners.
 */
function event(string $event, mixed ...$args): void
{
    EventDispatcher::getInstance()->dispatch($event, ...$args);
}

/**
 * Registers a listener for a specific event.
 *
 * @param string  $name     the name of the event to listen for
 * @param Closure $callback the callback to execute when the event is triggered
 */
function listen(string $name, Closure $callback): void
{
    EventDispatcher::getInstance()->addListener(new Event($name, $callback));
}
