<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ApplicationTest extends TestCase
{
    public function testApplication(): void
    {
        $app = Application::getInstance();
        $this->assertInstanceOf('\Hazaar\Application', $app);
        $this->assertInstanceOf('\Hazaar\Application\Config', $app->config);
        $this->assertInstanceOf('\Hazaar\Application\Router', $app->router);
        $this->assertInstanceOf('\Hazaar\Application\Request', $app->request);
        $this->assertInstanceOf('\Hazaar\Application\Request\Cli', $app->request);
    }
}
