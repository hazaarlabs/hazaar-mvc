<?php

namespace Hazaar\Events;

/**
 * Event Class.
 *
 * Represents a single event that can be listened for and triggered.
 * An Event object typically encapsulates a callback function that is executed
 * when the event is dispatched by the EventDispatcher.
 *
 * Example Usage:
 * ```php
 * // Create an event listener using the static listen method
 * $loginListener = Event::listen('user.login', function($username, $status) {
 *     echo "User '{$username}' login attempt status: {$status}";
 * });
 *
 * // Manually trigger the event (usually done by the dispatcher)
 * // $loginListener->trigger('JohnDoe', 'Success');
 *
 * // Check if the listener is registered
 * if ($loginListener->isRegistered()) {
 *     echo "Login listener is active.";
 * }
 *
 * // Unregister the listener
 * $loginListener->unregister();
 * ```
 */
class Event
{
    /**
     * @var string The name of the event (e.g., 'user.login', 'order.created').
     */
    private string $name;

    /**
     * @var ?\Closure the callback function to execute when the event is triggered
     */
    private ?\Closure $callback = null;

    /**
     * @var bool flag indicating whether the event listener is currently registered with the dispatcher
     */
    private bool $isRegistered = false;

    /**
     * Constructor for the Event class.
     *
     * Initializes a new Event object with a specific name.
     * Note: It's generally recommended to use the static `listen` method to create and register events.
     *
     * @param string $event the name of the event
     */
    public function __construct(string $event, ?\Closure $callback = null)
    {
        $this->name = $event;
        $this->callback = $callback;
    }

    /**
     * Gets the name of the event.
     *
     * @return string the name of the event
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Checks if the event listener is currently registered with the EventDispatcher.
     *
     * @return bool true if the event listener is registered, false otherwise
     */
    public function isRegistered(): bool
    {
        return $this->isRegistered;
    }

    /**
     * Triggers the event's callback function.
     *
     * Executes the associated Closure, passing any provided arguments to it.
     * Throws a RuntimeException if no callback is registered.
     *
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @throws \RuntimeException if no callback is set for this event
     */
    public function trigger(mixed ...$args): void
    {
        if (!$this->callback instanceof \Closure) {
            throw new \RuntimeException('No callback registered for this event.');
        }
        call_user_func($this->callback, ...$args);
    }

    /**
     * Unregisters the event listener from the EventDispatcher.
     *
     * If the listener is registered, it removes itself from the dispatcher
     * and updates its registration status.
     */
    public function unregister(): void
    {
        if (!$this->isRegistered) {
            return;
        }
        EventDispatcher::getInstance()->removeListener($this->name, $this);
        $this->isRegistered = false;
    }

    /**
     * Static factory method to create and register an event listener.
     *
     * This is the preferred way to create and register new event listeners.
     * It creates a new Event instance, assigns the callback, registers it
     * with the EventDispatcher, and returns the created Event object.
     *
     * @param string   $event    the name of the event to listen for
     * @param \Closure $callback the function to execute when the event is triggered
     *
     * @return Event the newly created and registered Event object
     */
    public static function listen(string $event, \Closure $callback): Event
    {
        $event = new self($event, $callback);
        EventDispatcher::getInstance()->addListener($event);
        $event->isRegistered = true;

        return $event;
    }
}
