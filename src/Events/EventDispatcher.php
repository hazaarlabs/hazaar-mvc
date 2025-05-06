<?php

namespace Hazaar\Events;

/**
 * EventDispatcher Class.
 *
 * Manages the registration and dispatching of events throughout the application.
 * This class follows the Singleton pattern to ensure a single instance manages all events.
 * Listeners are registered based on the type hint of the first parameter of their `handle` method.
 *
 * Example Usage:
 * ```php
 * // Define an event
 * class UserLoggedInEvent {
 *     use Hazaar\Events\Dispatchable;
 *     public $userId;
 *     public function __construct(int $userId) { $this->userId = $userId; }
 * }
 *
 * // Define a listener
 * class UserLoginListener {
 *     public function handle(UserLoggedInEvent $event) {
 *         echo "User logged in: " . $event->userId;
 *     }
 * }
 *
 * // Get the singleton instance
 * $dispatcher = EventDispatcher::getInstance();
 *
 * // Add the listener
 * $dispatcher->addListener(new UserLoginListener());
 *
 * // Dispatch the event using the Dispatchable trait
 * UserLoggedInEvent::dispatch(123); // Output: User logged in: 123
 * ```
 */
class EventDispatcher
{
    /**
     * @var ?EventDispatcher the singleton instance of the EventDispatcher
     */
    private static ?EventDispatcher $instance = null;

    /**
     * @var array<string,object> stores listeners keyed by event name
     */
    private array $listeners = [];

    /**
     * @var array<string,object> stores events to be dispatched
     */
    private array $dispatchQueue = [];

    /**
     * Private constructor to prevent direct instantiation.
     * Use `getInstance()` to get the singleton instance.
     */
    private function __construct()
    {
        // Private constructor to prevent instantiation
    }

    /**
     * Private clone method to prevent cloning of the singleton instance.
     */
    private function __clone()
    {
        // Prevent cloning
    }

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
     * Scans a directory for listener classes, instantiates them, and adds them.
     * Assumes listener classes are in the 'App\Listener' namespace and filenames match class names.
     *
     * @param string $listenerDir the directory path containing listener class files
     */
    public function withEvents(string $listenerDir): void
    {
        $files = scandir($listenerDir);
        foreach ($files as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            $listenerFile = $listenerDir.DIRECTORY_SEPARATOR.$file;
            if (!is_file($listenerFile)) {
                continue;
            }

            include_once $listenerFile;
            $listenerClass = 'App\Listener\\'.basename($file, '.php');
            if (!class_exists($listenerClass)) {
                continue;
            }
            $reflectionClass = new \ReflectionClass($listenerClass);
            $this->addListener($reflectionClass->newInstance());
        }
    }

    /**
     * Adds an event listener object to the dispatcher.
     *
     * The listener is registered based on the type hint of the first parameter
     * of its `handle` method. The listener object must have a public `handle` method.
     *
     * @param object $listener the listener object to add
     *
     * @return bool True if the listener was added successfully, false otherwise (e.g., no handle method, invalid type hint).
     */
    public function addListener(object $listener): bool
    {
        $reflectionClass = new \ReflectionClass($listener);
        if (!$reflectionClass->isInstantiable()) {
            return false;
        }
        if (!$reflectionClass->hasMethod('handle')) {
            return false;
        }
        $handleMethod = $reflectionClass->getMethod('handle');
        $parameters = $handleMethod->getParameters();
        if (0 === count($parameters)) {
            return false;
        }
        $type = $parameters[0]->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }
        $firstParameterType = $type->getName();
        if (!class_exists($firstParameterType)) {
            return false;
        }
        $this->listeners[$firstParameterType][] = $listener;

        return true;
    }

    /**
     * Registers a listener class for a specific event class name.
     * This method is less type-safe than `addListener` as it relies on string class names.
     * It's generally recommended to use `addListener` with instantiated objects.
     *
     * @param string $event    the fully qualified class name of the event
     * @param string $listener the fully qualified class name of the listener
     *
     * @deprecated use addListener(new $listener()) instead for better type safety and clarity
     */
    public function listen(string $event, string $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = new $listener();
    }

    /**
     * Retrieves all registered listener objects for a given event class name.
     *
     * @param string $event the fully qualified class name of the event
     *
     * @return array<object> an array of listener objects, or an empty array if none are registered
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * Removes all registered listeners from the dispatcher.
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
    }

    /**
     * Dispatches an event object to all registered listeners.
     *
     * Finds listeners registered for the specific class of the event object.
     * If a listener implements the `Queuable` interface, the event is added
     * to a queue for later processing via `dispatchQueue()`. Otherwise, the
     * listener's `handle` method is called immediately with the event object.
     *
     * @param object $event the event object to dispatch
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        if (!isset($this->listeners[$eventName])) {
            return;
        }
        foreach ($this->listeners[$eventName] as $listener) {
            if ($listener instanceof Queuable) {
                $this->dispatchQueue[$eventName][] = $event;

                continue;
            }
            $listener->handle($event);
        }
    }

    /**
     * Dispatches all events currently held in the queue.
     *
     * Iterates through the queued events and calls the `handle` method
     * of the corresponding listeners. The queue is cleared after processing.
     *
     * @param null|string $eventName the fully qualified class name of the event to dispatch from the queue, or null to dispatch all queued events
     */
    public function dispatchQueue(?string $eventName = null): void
    {
        if (null === $eventName) {
            foreach ($this->dispatchQueue as $events) {
                foreach ($events as $event) {
                    foreach ($this->listeners[get_class($event)] as $listener) {
                        $listener->handle($event);
                    }
                }
            }
            $this->dispatchQueue = [];
        } elseif (isset($this->dispatchQueue[$eventName])) {
            foreach ($this->dispatchQueue[$eventName] as $event) {
                foreach ($this->listeners[get_class($event)] as $listener) {
                    $listener->handle($event);
                }
            }
            unset($this->dispatchQueue[$eventName]);
        }
    }
}
