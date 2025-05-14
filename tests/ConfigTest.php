<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Config;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ConfigTest extends TestCase
{
    public function testBasicConfig(): void
    {
        $config = new Config();
        $this->assertInstanceOf(Config::class, $config);
        $config->loadFromArray([
            'app' => [
                'name' => 'Hazaar',
                'version' => '1.0.0',
                'locale' => 'en',
            ],
        ]);
        $this->assertEquals('Hazaar', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertEquals('en', $config->get('app.locale'));
    }

    public function testConfigWithDotNotation(): void
    {
        $config = new Config();
        $config->loadFromArray([
            'app.name' => 'Hazaar',
            'app.version' => '1.0.0',
            'app.locale' => 'en',
        ]);
        $this->assertEquals('Hazaar', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertEquals('en', $config->get('app.locale'));
    }

    public function testConfigFromFileWithProductionEnvironment(): void
    {
        $config = new Config();
        $config->setBasePath(__DIR__.'/app/configs/');
        $config->setEnvironment('production');
        $this->assertEquals('production', $config->getEnvironment());
        $result = $config->loadFromFile('application');
        $this->assertTrue($result);
        $this->assertEquals('PHPUnit - Test Application', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertEquals('en_AU.UTF-8', $config->get('app.locale'));
    }

    public function testConfigFromFileWithNoEnvironment(): void
    {
        $config = new Config(__DIR__.'/app/configs/application.json');
        $this->assertEquals('PHPUnit - Test Application', $config['production']['app']['name']);
        $this->assertEquals('1.0.0', $config->get('production.app.version'));
        $this->assertEquals('en_AU.UTF-8', $config->get('production.app.locale'));
    }

    public function testConfigFromFileWithDevelopmentEnvironment(): void
    {
        $config = new Config();
        $config->setBasePath(__DIR__.'/app/configs/');
        $config->setEnvironment('development');
        $result = $config->loadFromFile('application');
        $this->assertTrue($result);
        $this->assertEquals('PHPUnit - Test Application', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertEquals('en_AU.UTF-8', $config->get('app.locale'));
        $this->assertTrue($config->get('app.debug'));
    }

    public function testConfigFileWithImport(): void
    {
        $config = new Config();
        $config->setBasePath(__DIR__.'/app/configs/');
        $config->setEnvironment('production');
        $result = $config->loadFromArray([
            'production' => [
                'import' => [
                    'application.json:production',
                ],
                'app' => [
                    'name' => 'Test Application',
                ],
            ],
            'development' => [
                'include' => 'production',
                'app' => [
                    'name' => 'DevApp',
                ],
            ],
        ]);
        $this->assertTrue($result);
        $this->assertEquals('Test Application', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertEquals('en_AU.UTF-8', $config->get('app.locale'));
        $this->assertFalse($config->get('app.debug'));
        $this->assertNotEquals('DevApp', $config['app']['name']);

        $config->setEnvironment('development');
        $this->assertEquals('DevApp', $config['app']['name']);
        $this->assertFalse($config->get('app.debug')); // This should be false because we only include the production config
    }

    public function testConfigFileWithImportNoEnvironment(): void
    {
        $config = new Config();
        $config->setBasePath(__DIR__.'/app/configs/');
        $result = $config->loadFromArray([
            'import' => [
                'application.json',
            ],
        ]);
        $this->assertTrue($result);
        $this->assertEquals('PHPUnit - Test Application', $config['production']['app']['name']);
    }

    public function testConfigWithChangeEnvironment(): void
    {
        $config = new Config();
        $config->setBasePath(__DIR__.'/app/configs/');
        $config->setEnvironment('production');
        $result = $config->loadFromArray([
            'production' => [
                'app' => [
                    'name' => 'Production Application',
                    'version' => '1.0.0',
                    'locale' => 'en_AU.UTF-8',
                    'debug' => false,
                ],
            ],
            'development' => [
                'include' => 'production',
                'app' => [
                    'name' => 'Development Application',
                    'debug' => true,
                ],
            ],
        ]);
        $this->assertTrue($result);
        $this->assertEquals('Production Application', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertEquals('en_AU.UTF-8', $config->get('app.locale'));
        $this->assertFalse($config->get('app.debug'));
        $this->assertNotEquals('Development Application', $config['app']['name']);

        $config->setEnvironment('development');
        $this->assertEquals('Development Application', $config['app']['name']);
        $this->assertEquals('1.0.0', $config->get('app.version'));
        $this->assertTrue($config->get('app.debug'));
    }
}
