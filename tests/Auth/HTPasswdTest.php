<?php

declare(strict_types=1);

namespace Hazaar\Tests\Auth;

use Hazaar\Auth\Adapter\HTPasswd;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class HTPasswdTest extends TestCase
{
    private mixed $auth;

    public function setUp(): void
    {
        $this->auth = new HTPasswd(['passwdFile' => __DIR__.'/.passwd']);
        $this->auth->create('test', 'test');
    }

    public function tearDown(): void
    {
        unlink(__DIR__.'/.passwd');
    }

    public function testAuthSuccess(): void
    {
        $this->assertTrue($this->auth->authenticate('test', 'test'));
        $this->assertTrue($this->auth->authenticated());
    }

    public function testAuthFail(): void
    {
        $this->assertFalse($this->auth->authenticate('test', 'fail'));
        $this->assertFalse($this->auth->authenticated());
    }

    public function testAuthClear(): void
    {
        $this->assertTrue($this->auth->authenticate('test', 'test'));
        $this->assertTrue($this->auth->authenticated());
        $this->assertTrue($this->auth->clear());
        $this->assertFalse($this->auth->authenticated());
    }

    public function testAuthCreateUser(): void
    {
        $this->assertTrue($this->auth->create('test2', 'test2'));
        $this->assertTrue($this->auth->authenticate('test2', 'test2'));
        $this->assertTrue($this->auth->authenticated());
    }

    public function testAuthCreateUserFail(): void
    {
        $this->assertFalse($this->auth->create('test', 'test'));
        $this->assertFalse($this->auth->create('test', ''));
    }

    public function testAuthDeleteUser(): void
    {
        $this->assertTrue($this->auth->create('test2', 'test2'));
        $this->assertTrue($this->auth->authenticate('test2', 'test2'));
        $this->assertTrue($this->auth->authenticated());
        $this->assertTrue($this->auth->delete('test2'));
        $this->assertFalse($this->auth->authenticate('test2', 'test2'));
        $this->assertFalse($this->auth->authenticated());
    }
}
