<?php

declare(strict_types=1);

use Hazaar\Warlock\Channel;
use Hazaar\Warlock\Client;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class WarlockTest extends TestCase
{
    /**
     * Configuration for the Warlock client.
     *
     * @var array<string, mixed>
     */
    private static array $config = [
        'host' => 'localhost',
        'port' => 13080,
        'accessKey' => 'test',
    ];

    public function testCanConnect(): void
    {
        $warlock = new Client(self::$config);
        $this->assertFalse($warlock->connected());
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->connected());
        $this->assertTrue($warlock->disconnect());
        $this->assertFalse($warlock->connected());
    }

    public function testCanSendTrigger(): void
    {
        $warlock = new Client(self::$config);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->trigger('test', 'Hello, World!'));
        $this->assertTrue($warlock->disconnect());
    }

    public function testCanSubscribe(): void
    {
        $warlock = new Client(self::$config);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->subscribe('test', function ($data) {
            $this->assertEquals('test', $data);
        }));
        $this->assertTrue($warlock->trigger('test', 'test', true));
        $warlock->wait(5);
        $this->assertTrue($warlock->unsubscribe('test'));
        $this->assertTrue($warlock->disconnect());
    }

    public function testCanAuthenticate(): void
    {
        $warlock = new Client(self::$config);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->authenticate('test', 'test'));
        $this->assertTrue($warlock->disconnect());
    }

    public function testCanRunClosure(): void
    {
        $warlock = new Client(self::$config);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->authenticate('test', 'test'));
        $result = $warlock->exec(function () {Channel::trigger('test', 'Hello, World!'); });
        $this->assertIsString($result);
        $this->assertTrue($warlock->disconnect());
    }

    public function testCanRunEndpoint(): void
    {
        $warlock = new Client(self::$config);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->authenticate('test', 'test'));
        $result = $warlock->exec(['App\Service\Test', 'doTheThing']);
        $this->assertIsString($result);
        $this->assertTrue($warlock->disconnect());
    }
}
