<?php

namespace Hazaar\Events;

/**
 * EventDispatcher Class.
 *
 * Manages the registration and dispatching of events throughout the application.
 * This class follows the Singleton pattern to ensure a single instance manages all events.
 *
 * Example Usage:
 * ```php
 * // Get the singleton instance
 * $dispatcher = EventDispatcher::getInstance();
 *
 * // Create an event listener (assuming Event class or similar structure)
 * $listener = new Event(function($arg1, $arg2) {
 *     echo "Event triggered with: " . $arg1 . ", " . $arg2;
 * });
 *
 * // Add the listener for a specific event name
 * $dispatcher->addListener('user.login', $listener);
 *
 * // Dispatch the event with arguments
 * $dispatcher->dispatch('user.login', 'JohnDoe', 'Success');
 *
 * // Remove the listener
 * $dispatcher->removeListener('user.login', $listener);
 * ```
 */
class EventDispatcher
{
    /**
     * @var ?EventDispatcher the singleton instance of the EventDispatcher
     */
    private static ?EventDispatcher $instance = null;

    /**
     * @var array<string, array<Event>> stores listeners keyed by event name
     */
    private array $listeners = [];

    /**
     * Gets the singleton instance of the EventDispatcher.
     *
     * Ensures that only one instance of the EventDispatcher exists. If an instance
     * does not exist, it creates one.
     *
     * @return EventDispatcher the singleton instance
     */
    public static function getInstance(): EventDispatcher
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Adds an event listener for a specific event name.
     *
     * Registers an Event object to be triggered when the specified event name is dispatched.
     * Multiple listeners can be added for the same event name.
     *
     * @param string $name  The name of the event to listen for (e.g., 'user.created', 'order.processed').
     * @param Event  $event the Event object that will handle the event
     */
    public function addListener(string $name, Event $event): void
    {
        $this->listeners[$name][] = $event;
    }

    /**
     * Removes an event listener for a specific event name.
     *
     * Deregisters a specific Event object from the specified event name.
     * If the event name or the specific listener doesn't exist, the method does nothing.
     *
     * @param string $name  the name of the event the listener was registered for
     * @param Event  $event the specific Event object to remove
     */
    public function removeListener(string $name, Event $event): void
    {
        if (!isset($this->listeners[$name])) {
            return;
        }
        foreach ($this->listeners[$name] as $key => $listener) {
            if ($listener === $event) {
                unset($this->listeners[$name][$key]);
            }
        }
    }

    /**
     * Dispatches an event to all registered listeners.
     *
     * Triggers all Event objects registered for the given event name, passing
     * any additional arguments provided to each listener's trigger method.
     *
     * @param string $event   the name of the event to dispatch
     * @param mixed  ...$args Optional arguments to pass to the event listeners.
     */
    public function dispatch(string $event, mixed ...$args): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }
        foreach ($this->listeners[$event] as $listener) {
            $listener->trigger(...$args);
        }
    }
}
