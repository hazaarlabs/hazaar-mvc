<?php

declare(strict_types=1);

use Hazaar\Warlock\Client;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class WarlockTest extends TestCase
{
    public function testCanConnect(): void
    {
        $warlock = new Client();
        $this->assertFalse($warlock->connected());
        $this->assertTrue($warlock->connect('localhost', 13080));
        $this->assertTrue($warlock->connected());
        $this->assertTrue($warlock->disconnect());
        $this->assertFalse($warlock->connected());
    }

    public function testCanSendTrigger(): void
    {
        $warlock = new Client();
        $this->assertTrue($warlock->connect('localhost', 13080));
        $this->assertTrue($warlock->trigger('test', 'test'));
        $this->assertTrue($warlock->disconnect());
    }
}
