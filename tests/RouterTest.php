<?php

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\Application\Request\HTTP;
use Hazaar\Application\Router\Advanced;
use Hazaar\Application\Router\Annotated;
use Hazaar\Application\Router\Basic;
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
        $this->config = new Config('application', null, [
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
        $router = new Basic(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Test', $router->getControllerName());
        $this->assertEquals('index', $router->getActionName());
        $args = ['arg1', 'arg2', 'arg3'];
        $this->assertEquals($args, $router->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }

    public function testBasicRouterWithActionController(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ]);
        $router = new Basic(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Index', $router->getControllerName());
        $this->assertEquals('index', $router->getActionName());
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }

    public function testBasicRouter404Controller(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/bad/missing',
        ]);
        $router = new Basic(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Bad', $router->getControllerName());
        $this->assertEquals('missing', $router->getActionName());
        $this->expectException('Hazaar\Application\Router\Exception\ControllerNotFound');
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }

    public function testBasicRouter404Action(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index/missing',
        ]);
        $router = new Basic(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Index', $router->getControllerName());
        $this->assertEquals('missing', $router->getActionName());
        $this->expectException('Hazaar\Controller\Exception\ActionNotFound');
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }

    public function testAdvancedRouter(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/foo/word',
        ]);
        $router = new Advanced(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Test', $router->getControllerName());
        $this->assertEquals('foo', $router->getActionName());
        $this->assertEquals(['word'], $router->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }

    public function testCustomRouter(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/word',
        ]);
        $router = new Custom(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Test', $router->getControllerName());
        $this->assertEquals('bar', $router->getActionName());
        $this->assertEquals(['word'], $router->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }

    public function testAnnotatedRouter(): void
    {
        $request = new HTTP([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test/1234',
        ]);
        $router = new Annotated(Application::getInstance(), $this->config);
        $this->assertTrue($router->evaluateRequest($request));
        $this->assertEquals('Api', $router->getControllerName());
        $this->assertEquals('testGET', $router->getActionName());
        $this->assertEquals(['id' => 1234], $router->getActionArgs());
        $this->assertInstanceOf('Hazaar\Controller\Response', $router->__run($request));
    }
}
