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
     * @var array<string,object> stores listeners keyed by event name
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
            if (!$reflectionClass->isInstantiable()) {
                continue;
            }
            if (!$reflectionClass->hasMethod('handle')) {
                continue;
            }
            $handleMethod = $reflectionClass->getMethod('handle');
            $parameters = $handleMethod->getParameters();
            if (0 === count($parameters)) {
                continue;
            }
            $type = $parameters[0]->getType();
            if ($type instanceof \ReflectionNamedType) {
                $firstParameterType = $type->getName();
                $this->addListener($firstParameterType, $reflectionClass->newInstance());
            }
        }
    }

    public function addListener(string $type, object $listener): void
    {
        $this->listeners[$type][] = $listener;
    }

    public function listen(string $event, string $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = new $listener();
    }

    /**
     * @return array<object>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    public function clearListeners(): void
    {
        $this->listeners = [];
    }

    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        if (!isset($this->listeners[$eventName])) {
            return;
        }
        foreach ($this->listeners[$eventName] as $listener) {
            $listener->handle($event);
        }
    }
}
