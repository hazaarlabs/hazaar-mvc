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
        $authResult = $authMock->authenticate('test', 'test');
        $this->assertIsArray($authResult);
        $this->assertArrayHasKey('identity', $authResult);
        $this->assertArrayHasKey('credential', $authResult);
        $this->assertEquals($mockData['identity'], $authResult['identity']);
        $this->assertEquals($mockData['credential'], $authResult['credential']);
        $this->assertTrue($authMock->authenticated());
    }
}
