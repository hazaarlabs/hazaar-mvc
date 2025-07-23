<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use App\Controller\Index;
use App\Controller\Test;
use Hazaar\Application\Config;
use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Controller\Exception\ActionNotFound;
use Hazaar\Controller\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RouterTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $config;

    public function setUp(): void
    {
        $this->config = Config::getInstance('application', null, [
            'router' => [
                'controller' => 'index',
                'action' => 'index',
            ],
        ])->toArray();
    }

    public function testBasicRouterWithBasicController(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/index/arg1/arg2/arg3',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertInstanceOf(Route::class, $route = $router->evaluateRequest($request));
        $this->assertInstanceOf(Test::class, $controller = $route->getController());
        $this->assertEquals('index', $route->getAction());
        $args = ['arg1', 'arg2', 'arg3'];
        $this->assertEquals($args, $route->getActionArgs());
        $this->assertInstanceOf(Response::class, $controller->run($route));
    }

    public function testBasicRouterWithActionController(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertInstanceOf(Route::class, $route = $router->evaluateRequest($request));
        $this->assertInstanceOf(Index::class, $controller = $route->getController());
        $this->assertEquals('index', $route->getAction());
        $this->assertInstanceOf(Response::class, $controller->run($route));
    }

    public function testBasicRouter404Controller(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/bad/missing',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertNull($router->evaluateRequest($request));
    }

    public function testBasicRouter404Action(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index/missing',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertInstanceOf(Route::class, $route = $router->evaluateRequest($request));
        $this->assertInstanceOf(Index::class, $controller = $route->getController());
        $this->assertEquals('missing', $route->getAction());
        $this->expectException(ActionNotFound::class);
        $this->assertInstanceOf(Response::class, $controller->run($route));
    }

    public function testAdvancedRouter(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/foo/word',
        ]);
        $this->config['type'] = 'advanced';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertInstanceOf(Route::class, $route = $router->evaluateRequest($request));
        $this->assertTrue($router->initialise());
        $this->assertInstanceOf(Test::class, $controller = $route->getController());
        $this->assertEquals('foo', $route->getAction());
        $this->assertEquals(['word'], $route->getActionArgs());
        $this->assertInstanceOf(Response::class, $controller->run($route));
    }

    public function testCustomRouter(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/hellothere',
        ]);
        $this->config['type'] = 'file';
        $this->config['applicationPath'] = __DIR__.'/app';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertInstanceOf(Route::class, $route = $router->evaluateRequest($request));
        $this->assertInstanceOf(Test::class, $controller = $route->getController());
        $this->assertEquals('bar', $route->getAction());
        $this->assertEquals(['word' => 'hellothere'], $route->getActionArgs());
        $this->assertInstanceOf(Response::class, $controller->run($route));
    }

    public function testNoneRouter(): void
    {
        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/hellothere',
        ]);
        $this->config['type'] = 'none';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise());
        $this->assertNull($router->evaluateRequest($request));
    }
}
