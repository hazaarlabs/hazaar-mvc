<?php

use Hazaar\Events\Dispatchable;
use Hazaar\Events\Event;
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

/**
 * @internal
 */
class EventsTest extends TestCase
{
    public function testRegisterEventListener(): void
    {
        $listener = new TestListener();

        // Register the listener
        Event::listen(TestEvent::class, $listener::class);

        // Dispatch the event
        $event = TestEvent::dispatch('test');

        // Assert that the event was dispatched
        $this->assertTrue($event->dispatched);
        $this->assertEquals('test', $event->name);
    }
}
