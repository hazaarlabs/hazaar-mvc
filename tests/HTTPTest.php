<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class HTTPTest extends TestCase
{
    public function testRequest(): void
    {
        $request = new Request('https://www.google.com', 'GET');
        $this->assertInstanceOf('\Hazaar\HTTP\Request', $request);
        $this->assertEquals('GET', $request->method);
        $url = $request->url();
        $this->assertInstanceOf('Hazaar\HTTP\URL', $url);
        $this->assertEquals('https', $url->scheme());
        $this->assertEquals('www.google.com', $url->host());
        $this->assertEquals('/', $url->path());
        $this->assertEquals(443, $url->port());
        $this->assertEquals('https://www.google.com/', $url->toString());
    }

    public function testSendRequest(): void
    {
        $request = new Request('https://www.google.com', 'GET');
        $client = new Client();
        $response = $client->send($request);
        $this->assertInstanceOf('\Hazaar\HTTP\Response', $response);
        $this->assertEquals(200, $response->status);
        $this->assertStringContainsString('google', $response->body);
        $args = [];
        $this->assertEquals('text/html', $response->getContentType($args));
        $this->assertArrayHasKey('charset', $args);
        $this->assertEquals('ISO-8859-1', $args['charset']);
    }
}
