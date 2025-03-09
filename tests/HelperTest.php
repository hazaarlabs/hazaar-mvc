<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application;
use Hazaar\File\BTree;
use Hazaar\Util\Arr;
use Hazaar\Util\Str;
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
        $this->assertEquals('3:24:12', Str::uptime(12252));
        $this->assertEquals('0:00:00', Str::uptime(0));
        $this->assertEquals('1 day 0:00:00', Str::uptime(86400));
        $this->assertEquals('1 day 10:17:36', Str::uptime(123456));
        $this->assertEquals('7 days 13:45:21', Str::uptime(654321));
        $this->assertEquals('365 days 0:31:30', Str::uptime(31537890));
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

    public function testStrMatchReplace(): void
    {
        $this->assertEquals('Hello World', Str::matchReplace('Hello {{name}}', ['name' => 'World']));
        $this->assertEquals('Hello World', Str::matchReplace('Hello {{name}}', ['name' => 'World', 'missing' => '']));
        $this->assertEquals('Hello World', Str::matchReplace('Hello {{name}}', ['name' => 'World', 'missing' => ''], true));
        $this->assertEquals('Hello ', Str::matchReplace('Hello {{name}}', ['missing' => '']));
        $this->assertNull(Str::matchReplace('Hello {{name}}', ['missing' => ''], true));
    }

    public function testStrIsReserved(): void
    {
        $this->assertTrue(Str::isReserved('class'));
        $this->assertTrue(Str::isReserved('function'));
        $this->assertTrue(Str::isReserved('namespace'));
        $this->assertTrue(Str::isReserved('trait'));
        $this->assertTrue(Str::isReserved('interface'));
        $this->assertTrue(Str::isReserved('extends'));
        $this->assertTrue(Str::isReserved('implements'));
        $this->assertTrue(Str::isReserved('use'));
        $this->assertTrue(Str::isReserved('public'));
        $this->assertTrue(Str::isReserved('protected'));
        $this->assertTrue(Str::isReserved('private'));
        $this->assertTrue(Str::isReserved('static'));
        $this->assertTrue(Str::isReserved('final'));
        $this->assertTrue(Str::isReserved('abstract'));
        $this->assertTrue(Str::isReserved('const'));
        $this->assertTrue(Str::isReserved('var'));
        $this->assertTrue(Str::isReserved('callable'));
        $this->assertTrue(Str::isReserved('as'));
        $this->assertTrue(Str::isReserved('try'));
        $this->assertTrue(Str::isReserved('catch'));
        $this->assertTrue(Str::isReserved('throw'));
        $this->assertTrue(Str::isReserved('goto'));
        $this->assertTrue(Str::isReserved('return'));
        $this->assertTrue(Str::isReserved('exit'));
        $this->assertTrue(Str::isReserved('die'));
        $this->assertTrue(Str::isReserved('echo'));
        $this->assertTrue(Str::isReserved('print'));
    }

    public function testStrFromBytes(): void
    {
        $this->assertEquals('1KB', Str::fromBytes(1024));
        $this->assertEquals('1KB', Str::fromBytes(1100));
        $this->assertEquals('1.07KB', Str::fromBytes(1100, 'K', 2));
        $this->assertEquals('1MB', Str::fromBytes(1024 * 1024));
        $this->assertEquals('1GB', Str::fromBytes(1024 * 1024 * 1024));
        $this->assertEquals('1TB', Str::fromBytes(1024 * 1024 * 1024 * 1024));
        $this->assertEquals('1PB', Str::fromBytes(1024 * 1024 * 1024 * 1024 * 1024));
        $this->assertEquals('1EB', Str::fromBytes(1024 * 1024 * 1024 * 1024 * 1024 * 1024));
    }

    public function testStrToBytes(): void
    {
        $this->assertEquals(1024, Str::toBytes('1KB'));
        $this->assertEquals(1024, Str::toBytes('1 KB'));
        $this->assertEquals(1024, Str::toBytes('1.0KB'));
        $this->assertEquals(1024 * 1024, Str::toBytes('1MB'));
        $this->assertEquals(1024 * 1024 * 1024, Str::toBytes('1GB'));
        $this->assertEquals(1024 * 1024 * 1024 * 1024, Str::toBytes('1TB'));
        $this->assertEquals(1024 * 1024 * 1024 * 1024 * 1024, Str::toBytes('1PB'));
        $this->assertEquals(1024 * 1024 * 1024 * 1024 * 1024 * 1024, Str::toBytes('1EB'));
    }
}
