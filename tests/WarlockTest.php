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
        $warlock = new Client([
            'host' => 'localhost',
            'port' => 13080,
        ]);
        $this->assertFalse($warlock->connected());
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->connected());
        $this->assertTrue($warlock->disconnect());
        $this->assertFalse($warlock->connected());
    }

    public function testCanSendTrigger(): void
    {
        $warlock = new Client([
            'host' => 'localhost',
            'port' => 13080,
        ]);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->trigger('test', 'Hello, World!'));
        $this->assertTrue($warlock->disconnect());
    }

    public function testCanSubscribe(): void
    {
        $warlock = new Client([
            'host' => 'localhost',
            'port' => 13080,
        ]);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->subscribe('test', function ($data) {
            $this->assertEquals('test', $data);
        }));
        $this->assertTrue($warlock->trigger('test', 'test', true));
        $warlock->wait(5);
        $this->assertTrue($warlock->unsubscribe('test'));
        $this->assertTrue($warlock->disconnect());
    }

    public function testCanRunCode(): void
    {
        $warlock = new Client([
            'host' => 'localhost',
            'port' => 13080,
        ]);
        $this->assertTrue($warlock->connect());
        $result = $warlock->exec(function () {return 'Hello, World!'; });
        $this->assertTrue($result);
        $this->assertTrue($warlock->disconnect());
    }
}
