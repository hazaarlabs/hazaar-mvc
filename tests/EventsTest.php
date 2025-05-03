<?php

use Hazaar\Events\Event;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EventsTest extends TestCase
{
    public function testRegisterEventListener(): void
    {
        $executed = false;
        $event = Event::listen('test.event', function () use (&$executed) {
            $executed = true;
        });
        $this->assertInstanceOf(Event::class, $event);
        $this->assertTrue($event->isRegistered());
        $this->assertFalse($executed);
        $event->trigger();
        // @phpstan-ignore method.impossibleType
        $this->assertTrue($executed);
        $event->unregister();
        $this->assertFalse($event->isRegistered());
    }
}
