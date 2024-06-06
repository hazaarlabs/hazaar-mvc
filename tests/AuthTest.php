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
    public function testBasicAuth(): void
    {
        $authMock = $this->getMockBuilder(Basic::class)
            ->onlyMethods(['queryAuth'])
            ->getMock()
        ;
        $mockData = [
            'identity' => 'test',
            'credential' => $authMock->getCredentialHash('test'),
        ];
        $authMock->expects($this->once())
            ->method('queryAuth')
            ->willReturn($mockData)
        ;
        $this->assertTrue($authMock->authenticate('test', 'test'));
        $authData = $authMock->getAuthData();
        $this->assertIsArray($authData);
        $this->assertArrayHasKey('identity', $authData);
        $this->assertArrayHasKey('credential', $authData);
        $this->assertEquals($mockData['identity'], $authData['identity']);
        $this->assertEquals($mockData['credential'], $authData['credential']);
        $this->assertEquals($mockData['identity'], $authMock->get('identity'));
        $this->assertTrue($authMock->authenticated());
    }
}
