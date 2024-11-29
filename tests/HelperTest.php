<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\File\BTree;
use Hazaar\File;
use Hazaar\Map;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class HelperTest extends TestCase
{
    private Application $app;

    public function setUp(): void
    {
        $this->app = Application::getInstance();
    }

    public function testDotNotationFunctions(): void
    {
        $dot_notation = [
            'root.child1.child2' => 'value',
            'root.child1.child3' => 'value',
            'root.child4.child5' => 'value',
            'root.child6.child7' => 'value',
        ];
        $result = array_from_dot_notation($dot_notation);
        $this->assertArrayHasKey('root', $result);
        $this->assertIsArray($result['root']);
        $this->assertArrayHasKey('child1', $result['root']);
        $this->assertIsArray($result['root']['child1']);
        $this->assertArrayHasKey('child7', $result['root']['child6']);
    }

    public function testMapClass(): void
    {
        $map = new Map(['key' => 'value']);
        $this->assertTrue($map->has('key'));
        $this->assertEquals('value', $map->get('key'));
        $this->assertEquals('missing', $map->get('test', 'missing'));
        $this->assertArrayHasKey('key', $map->toArray());
        $this->assertEquals('value', $map->toArray()['key']);
    }

    public function testBTreeFile(): void
    {
        $btree = new BTree($this->app->getRuntimePath('test.btree'));
        $this->assertTrue($btree->set('key', 'value'));
        $this->assertEquals('value', $btree->get('key'));
        $this->assertTrue($btree->remove('key'));
        $this->assertNull($btree->get('key'));
        $this->assertTrue($btree->compact());
    }

    public function testUptime(): void
    {
        $interval = 12240;
        $this->assertEquals('3:24:00', uptime($interval));
    }

    public function testAge(): void
    {
        $this->assertEquals(45, age('1978-12-13'));
    }
}
