<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\Arr;
use Hazaar\File\BTree;
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
        $result = Arr::fromDotNotation($dot_notation);
        $this->assertArrayHasKey('root', $result);
        $this->assertIsArray($result['root']);
        $this->assertArrayHasKey('child1', $result['root']);
        $this->assertIsArray($result['root']['child1']);
        $this->assertArrayHasKey('child7', $result['root']['child6']);
        $dot_notation_from_array = Arr::toDotNotation($result);
        $this->assertEquals($dot_notation, $dot_notation_from_array);
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

    public function testUptimeFunction(): void
    {
        $this->assertEquals('3:24:12', uptime(12252));
        $this->assertEquals('0:00:00', uptime(0));
        $this->assertEquals('1 day 0:00:00', uptime(86400));
        $this->assertEquals('1 day 10:17:36', uptime(123456));
        $this->assertEquals('7 days 13:45:21', uptime(654321));
        $this->assertEquals('365 days 0:31:30', uptime(31537890));
    }

    public function testAgeFunction(): void
    {
        $this->assertEquals(46, age('1978-12-13'));
    }

    public function testAKEFunctionWithDotNotation(): void
    {
        $array = [
            'key' => ['subkey' => 'value'],
            'items' => [
                ['name' => 'item1', 'type' => ['id' => 1, 'name' => 'type1']],
                ['name' => 'item2', 'type' => ['id' => 2, 'name' => 'type2']],
                ['name' => 'item3', 'type' => ['id' => 3, 'name' => 'type3']],
            ],
        ];
        $this->assertEquals('value', Arr::get($array, 'key.subkey'));
        $this->assertNull(Arr::get($array, 'key.missing'));
        $this->assertEquals('item2', Arr::get($array, 'items[1].name'));
        $this->assertEquals('item3', Arr::get($array, 'items(type.id=3).name'));
        $this->assertEquals('type2', Arr::get($array, 'items(name=item2).type.name'));
    }
}
