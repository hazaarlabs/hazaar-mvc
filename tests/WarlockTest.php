<?php

declare(strict_types=1);

use Hazaar\Warlock\Agent\Struct\Endpoint;
use Hazaar\Warlock\Channel;
use Hazaar\Warlock\Client;
use Hazaar\Warlock\Protocol;
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

    public function testCanCreateEndpointFromString(): void
    {
        $endpoint = Endpoint::create('App\Service\Test::doTheThing');
        $this->assertInstanceOf(Endpoint::class, $endpoint);
        $this->assertEquals('App\Service\Test', $endpoint->getTarget());
        $this->assertEquals('doTheThing', $endpoint->getMethod());
    }

    public function testCanCreateEndpointFromArray(): void
    {
        $endpoint = Endpoint::create(['App\Service\Test', 'doTheThing']);
        $this->assertInstanceOf(Endpoint::class, $endpoint);
        $this->assertEquals('App\Service\Test', $endpoint->getTarget());
        $this->assertEquals('doTheThing', $endpoint->getMethod());
    }

    public function testCanRunEndpointFromClosure(): void
    {
        $closure = function (): bool {
            return true;
        };
        $endpoint = Endpoint::create($closure);
        $this->assertInstanceOf(Endpoint::class, $endpoint);
        $this->assertInstanceOf('Hazaar\Util\Closure', $endpoint->getTarget());
        $this->assertEquals('__invoke', $endpoint->getMethod());
        $this->assertTrue($endpoint->run(new Protocol('test')));
        $this->assertStringStartsWith('function (): bool', $endpoint->getTarget()->getCode());
        $serialized = serialize($endpoint);
        $endpoint = unserialize($serialized);
        $this->assertInstanceOf(Endpoint::class, $endpoint);
        $this->assertTrue($endpoint->run(new Protocol('test')));
    }

    public function testCanRunEndpointFromArrowFunction(): void
    {
        $closure = fn () => true;
        $endpoint = Endpoint::create($closure);
        $this->assertInstanceOf(Endpoint::class, $endpoint);
        $this->assertInstanceOf('Hazaar\Util\Closure', $endpoint->getTarget());
        $this->assertEquals('__invoke', $endpoint->getMethod());
        $this->assertTrue($endpoint->run(new Protocol('test')));
    }

    public function testCanStoreKeyValue(): void
    {
        $warlock = new Client(self::$config);
        $this->assertTrue($warlock->connect());
        $this->assertTrue($warlock->authenticate('test', 'test'));
        $testValue = 'test_'.uniqid();
        $this->assertTrue($warlock->set('test_key', $testValue));
        $this->assertEquals($testValue, $warlock->get('test_key'));
        $this->assertTrue($warlock->del('test_key'));
        $this->assertNull($warlock->get('test_key'));
        $this->assertTrue($warlock->disconnect());
    }
}
