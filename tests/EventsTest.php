<?php

use Hazaar\Events\Dispatchable;
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
    // public function testRegisterEventListener(): void
    // {
    //     $event = Event::listen('test.event', function () use (&$executed) {
    //         $executed = true;
    //     });
    //     $this->assertInstanceOf(Event::class, $event);
    //     $this->assertTrue($event->isRegistered());
    //     $this->assertFalse($executed);
    //     $event->trigger();
    //     // @phpstan-ignore method.impossibleType
    //     $this->assertTrue($executed);
    //     $event->unregister();
    //     $this->assertFalse($event->isRegistered());
    // }
}
