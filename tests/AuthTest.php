<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Auth\Adapter\Basic;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AuthTest extends TestCase
{
    private mixed $authMock;
    private mixed $mockData;

    public function setUp(): void
    {
        $this->authMock = $this->getMockBuilder(Basic::class)
            ->onlyMethods(['queryAuth'])
            ->getMock()
        ;
        $this->mockData = [
            'identity' => 'test',
            'credential' => $this->authMock->getCredentialHash('test'),
        ];
        $this->authMock->expects($this->once())
            ->method('queryAuth')
            ->willReturn($this->mockData)
        ;
    }

    public function testBasicAuth(): void
    {
        $this->assertTrue($this->authMock->authenticate('test', 'test'));
        $authData = $this->authMock->getAuthData();
        $this->assertIsArray($authData);
        $this->assertArrayHasKey('identity', $authData);
        $this->assertArrayHasKey('credential', $authData);
        $this->assertEquals($this->mockData['identity'], $authData['identity']);
        $this->assertEquals($this->mockData['credential'], $authData['credential']);
        $this->assertEquals($this->mockData['identity'], $this->authMock->get('identity'));
        $this->assertTrue($this->authMock->authenticated());
        $this->assertTrue($this->authMock->deauth());
    }

    public function testBasicAuthFail(): void
    {
        $this->assertFalse($this->authMock->authenticate('test', 'fail'));
        $this->assertFalse($this->authMock->authenticated());
        $this->assertFalse($this->authMock->deauth());
    }
}