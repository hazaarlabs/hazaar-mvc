<?php

namespace Hazaar\Tests;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Dispatcher;
use Hazaar\Middleware\Interface\Middleware;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MiddlewareTest extends TestCase
{
    public function testMiddlewareHandlesRequest(): void
    {
        $middleware = new Dispatcher();
        $request = $this->createMock(Request::class);
        $finalHandler = function (Request $request) {
            $response = new Response();
            $response->setContent('Next middleware or controller called');

            return $response;
        };
        $middleware->add(new class implements Middleware {
            public function handle(Request $request, callable $next, mixed ...$args): Response
            {
                // Simulate some processing
                return $next($request);
            }
        });
        $response = $middleware->handle($request, $finalHandler);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Next middleware or controller called', $response->getContent());
    }

    public function testMiddlewareModifiesRequest(): void
    {
        $middleware = new Dispatcher();
        $request = new Request();
        $finalHandler = function (Request $request) {
            $response = new Response($request->getHeader('X-Test-Content-Type'));
            $response->setContent('Next middleware or controller called');

            return $response;
        };
        $middleware->add(new class implements Middleware {
            public function handle(Request $request, callable $next, mixed ...$args): Response
            {
                $request->setHeader('X-Test-Content-Type', 'application/text');

                return $next($request);
            }
        });
        $response = $middleware->handle($request, $finalHandler);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('application/text', $response->getContentType());
    }
}
