<?php

namespace Hazaar\Tests;

use Hazaar\Application\Config;
use Hazaar\Application\Request\HTTP;
use Hazaar\Application\Router;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RouterTest extends TestCase
{
    private Config $config;

    public function setUp(): void
    {
        $this->config = Config::getInstance('application', null, [
            'router' => [
                'controller' => 'index',
                'action' => 'index',
            ],
        ]);
    }

    public function testBasicRouterWithBasicController(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/index/arg1/arg2/arg3',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->assertInstanceOf('Application\Controllers\Test', $controller = $route->getController());
        $this->assertEquals('index', $route->getAction());
        $args = ['arg1', 'arg2', 'arg3'];
        $this->assertEquals($args, $route->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $controller->run($route));
    }

    public function testBasicRouterWithActionController(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->assertInstanceOf('Application\Controllers\Index', $controller = $route->getController());
        $this->assertEquals('index', $route->getAction());
        $this->assertInstanceOf('Hazaar\Controller\Response', $controller->run($route));
    }

    public function testBasicRouter404Controller(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/bad/missing',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->expectException('Hazaar\Application\Router\Exception\ControllerNotFound');
        $this->assertEquals('Bad', $controller = $route->getController());
    }

    public function testBasicRouter404Action(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index/missing',
        ]);
        $this->config['type'] = 'basic';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->assertInstanceOf('Application\Controllers\Index', $controller = $route->getController());
        $this->assertEquals('missing', $route->getAction());
        $this->expectException('Hazaar\Controller\Exception\ActionNotFound');
        $this->assertInstanceOf('Hazaar\Controller\Response', $controller->run($route));
    }

    public function testAdvancedRouter(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/foo/word',
        ]);
        $this->config['type'] = 'advanced';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Application\Controllers\Test', $controller = $route->getController());
        $this->assertEquals('foo', $route->getAction());
        $this->assertEquals(['word'], $route->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $controller->run($route));
    }

    public function testCustomRouter(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/word',
        ]);
        $this->config['type'] = 'file';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->assertInstanceOf('Application\Controllers\Test', $controller = $route->getController());
        $this->assertEquals('bar', $route->getAction());
        $this->assertEquals(['word'], $route->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $controller->run($route));
    }

    public function testAnnotatedRouter(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test/1234',
        ]);
        $this->config['type'] = 'annotated';
        $router = new Router($this->config);
        $this->assertTrue($router->initialise($request));
        $this->assertInstanceOf('Hazaar\Application\Route', $route = $router->getRoute());
        $this->assertEquals('Application\Controllers\Api', $controller = $route->getController());
        $this->assertEquals('testGET', $route->getAction());
        $this->assertEquals(['id' => 1234], $route->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $controller->run($route));
    }
}
