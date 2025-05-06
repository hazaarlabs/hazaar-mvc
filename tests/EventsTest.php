<?php

namespace Hazaar\Tests;

use Hazaar\Events\Dispatchable;
use Hazaar\Events\Event;
use Hazaar\Events\Queuable;
use PHPUnit\Framework\TestCase;

class TestEvent
{
    use Dispatchable;

    public function __construct(
        public string $name,
        public bool $dispatched = false
    ) {}
}

class TestListener
{
    public function handle(TestEvent $event): void
    {
        $event->dispatched = true;
    }
}

class QueuedListener extends TestListener implements Queuable {}

/**
 * @internal
 */
class EventsTest extends TestCase
{
    public function testRegisterEventListener(): void
    {
        $listener = new TestListener();

        // Register the listener
        Event::listen($listener);

        // Dispatch the event
        $event = TestEvent::dispatch('test');

        // Assert that the event was dispatched
        $this->assertTrue($event->dispatched);
        $this->assertEquals('test', $event->name);

        Event::clearListeners();
    }

    public function testQueuedEventListener(): void
    {
        $listener = new QueuedListener();

        // Register the listener
        Event::listen($listener);

        // Dispatch the queued event
        $event = TestEvent::dispatch('test');
        $this->assertFalse($event->dispatched);

        // Dispatch the queue
        Event::dispatchQueue(TestEvent::class);

        // Assert that the event was dispatched
        $this->assertTrue($event->dispatched);
        $this->assertEquals('test', $event->name);

        Event::clearListeners();
    }
}
