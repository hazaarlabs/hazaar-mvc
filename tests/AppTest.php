<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\Application\FilePath;
use Hazaar\Loader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AppTest extends TestCase
{
    public function testApp(): void
    {
        $app = Application::getInstance();
        $this->assertInstanceOf('\Hazaar\Application', $app);
        $this->assertInstanceOf('\Hazaar\Application\Config', $app->config);
        $this->assertInstanceOf('\Hazaar\Application\Router', $app->router);
    }

    public function testLoaderIsAvailable(): void
    {
        $loader = Loader::getInstance();
        $this->assertInstanceOf(Loader::class, $loader);
    }

    public function testLoaderHasPaths(): void
    {
        $controllers = Loader::getFilePath(FilePath::CONTROLLER);
        $this->assertIsString($controllers);
        $this->assertNotEmpty($controllers, 'Controller path should not be empty');
        $views = Loader::getFilePath(FilePath::VIEW);
        $this->assertIsString($views);
        $this->assertNotEmpty($views, 'View path should not be empty');
        $models = Loader::getFilePath(FilePath::MODEL);
        $this->assertIsString($models);
        $this->assertNotEmpty($models, 'Model path should not be empty');
        $libraries = Loader::getFilePath(FilePath::SUPPORT);
        $this->assertIsString($libraries);
        $this->assertNotEmpty($libraries, 'Library path should not be empty');
    }
}
