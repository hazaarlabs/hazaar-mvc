<?php

namespace HazaarTest;

class ApplicationTest extends \PHPUnit_Framework_TestCase {

    private $app;

    private $config_file;

    protected function setUp() {

        $this->config_file = realpath(APPLICATION_PATH . '/configs/application.ini');

        $this->app = new \Hazaar\Application('development', $this->config_file);

    }

    public function testCanFindConfig() {

        $this->assertNotNull($this->config_file);

        $this->assertTrue(file_exists($this->config_file));

    }

    public function testCanInitApplication() {

        $this->assertInstanceOf('Hazaar\Application', $this->app);

        $this->assertTrue($this->app->config->loaded());

    }

    public function testReadConfig() {

        $this->assertInstanceOf('Hazaar\Application\Config', $this->app->config);
        
        $this->assertEquals('Example Application', $this->app->config->app->name);
        
        $this->assertEquals('0.0.1', $this->app->config->app->version);

        $this->assertEquals(1, $this->app->config->app->debug);

    }
    
    public function testLoadIndex(){
        
        $this->assertInstanceOf('Hazaar\Application', $this->app->bootstrap());
        
    }

}