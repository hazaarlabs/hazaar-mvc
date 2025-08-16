<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Application\Runtime;
use Hazaar\File\BTree;
use Hazaar\Util\Arr;
use Hazaar\Util\BTree as BTree2;
use Hazaar\Util\Closure;
use Hazaar\Util\Exception\InvalidClosure;
use Hazaar\Util\GeoData;
use Hazaar\Util\Interval;
use Hazaar\Util\Str;
use Hazaar\Util\Version;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class UtilityTest extends TestCase
{
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
        $file = Runtime::getInstance()->getPath('test.btree');
        if (file_exists($file)) {
            unlink($file);
        }
        $btree = new BTree($file);
        $this->assertTrue($btree->set('key', 'value'));
        $this->assertEquals('value', $btree->get('key'));
        $this->assertTrue($btree->remove('key'));
        $this->assertNull($btree->get('key'));

        /**
         * Inserts 1000 unique key-value pairs into the B-tree and asserts that each insertion is successful.
         *
         * For each iteration:
         * - Generates a unique key using uniqid().
         * - Stores a value associated with the key in the $keyIndex array.
         * - Inserts the key-value pair into the B-tree using $btree->set().
         * - Asserts that the insertion returns true.
         */
        $keyIndex = [];
        $keylen = 16; // Set a fixed length for keys
        for ($i = 0; $i < 1000; ++$i) {
            $key = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $keylen)), 0, rand(2, $keylen));
            $keyIndex[$key] = 'value: '.$key;
            $this->assertTrue($btree->set((string) $key, $keyIndex[$key]));
        }

        /**
         * Iterates over each key-value pair in the $keyIndex array and asserts that
         * the value retrieved from the $btree using the string representation of the key
         * matches the expected value from $keyIndex.
         *
         * @param array  $keyIndex array of keys and their expected values
         * @param object $btree    B-tree object with a get method to retrieve values by key
         */
        foreach ($keyIndex as $testKey => $testValue) {
            $this->assertEquals($keyIndex[$testKey], $btree->get((string) $testKey));
        }
    }

    public function testBTree2File(): void
    {
        $file = Runtime::getInstance()->getPath('test.btree2');
        if (file_exists($file)) {
            unlink($file);
        }
        $keySize = 32; // Set a fixed length for keys
        $btree = new BTree2($file, $keySize);
        $this->assertTrue($btree->set('key', 'value'));
        $this->assertEquals('value', $btree->get('key'));
        $this->assertTrue($btree->remove('key'));
        $this->assertNull($btree->get('key'));

        /**
         * Inserts 1000 unique key-value pairs into the B-tree and asserts that each insertion is successful.
         *
         * For each iteration:
         * - Generates a unique key using uniqid().
         * - Stores a value associated with the key in the $keyIndex array.
         * - Inserts the key-value pair into the B-tree using $btree->set().
         * - Asserts that the insertion returns true.
         */
        $keyIndex = [];
        $keylen = 32; // Set a fixed length for keys
        for ($i = 0; $i < 1000; ++$i) {
            $key = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $keylen)), 0, rand(2, $keylen));
            $keyIndex[$key] = 'value: '.$key;
            $this->assertTrue($btree->set((string) $key, $keyIndex[$key]));
        }

        /**
         * Iterates over each key-value pair in the $keyIndex array and asserts that
         * the value retrieved from the $btree using the string representation of the key
         * matches the expected value from $keyIndex.
         *
         * @param array  $keyIndex array of keys and their expected values
         * @param object $btree    B-tree object with a get method to retrieve values by key
         */
        foreach ($keyIndex as $testKey => $testValue) {
            $this->assertEquals($keyIndex[$testKey], $btree->get((string) $testKey));
        }
    }

    public function testGeoData(): void
    {
        $geo = new GeoData();
        $this->assertArrayHasKey('AU', $geo->countries());
        $countryInfo = $geo->countryInfo('AU');
        $this->assertArrayHasKey('currency', $countryInfo);
        $this->assertArrayHasKey('languages', $countryInfo);
        $this->assertArrayHasKey('name', $countryInfo);
        $this->assertArrayHasKey('phone_code', $countryInfo);
        $this->assertArrayHasKey('continent', $countryInfo);
        $this->assertArrayHasKey('capital', $countryInfo);
        $this->assertEquals('Australia', $geo->countryName('AU'));
        $this->assertIsArray($a = $geo->countryContinent('AU'));
        $this->assertEquals('Oceania', $a['name']);
        // Assert array contains en-AU
        $this->assertContains('en-AU', $geo->countryLanguages('AU'));
        $this->assertIsArray($s = $geo->states('AU'));
        $this->assertArrayHasKey('NSW', $geo->states('AU'));
        $this->assertEquals(61, $geo->countryPhoneCode('AU'));
    }

    public function testUptimeFunction(): void
    {
        $this->assertEquals('3:24:12', Interval::uptime(12252));
        $this->assertEquals('0:00:00', Interval::uptime(0));
        $this->assertEquals('1 day 0:00:00', Interval::uptime(86400));
        $this->assertEquals('1 day 10:17:36', Interval::uptime(123456));
        $this->assertEquals('7 days 13:45:21', Interval::uptime(654321));
        $this->assertEquals('365 days 0:31:30', Interval::uptime(31537890));
    }

    public function testAgeFunction(): void
    {
        $this->assertEquals(46, Interval::age('1978-12-13'));
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

    public function testVersionClassBasic(): void
    {
        // Assuming Hazaar\Util\Version exists and follows SemVer principles
        // Need to add: use Hazaar\Util\Version; at the top of the file.
        $v1 = new Version('1.2.3');
        $this->assertEquals(1, $v1->getMajor());
        $this->assertEquals(2, $v1->getMinor());
        $this->assertEquals(3, $v1->getPatch());
        $this->assertNull($v1->getPreRelease());
        $this->assertNull($v1->getMetadata());
        $this->assertEquals('1.2.3', (string) $v1);

        $v2 = new Version('2.0.0-alpha.1+build.123');
        $this->assertEquals(2, $v2->getMajor());
        $this->assertEquals(0, $v2->getMinor());
        $this->assertEquals(0, $v2->getPatch());
        $this->assertEquals('alpha.1', $v2->getPreRelease());
        $this->assertEquals('build.123', $v2->getMetadata());
        $this->assertEquals('2.0.0-alpha.1+build.123', (string) $v2);
    }

    public function testVersionClassComparisons(): void
    {
        $v1 = new Version('1.2.3');
        $v2 = new Version('1.2.3'); // Same as v1
        $v3 = new Version('1.2.4');
        $v4 = new Version('1.3.0');

        $v5 = new Version('2.0.0-alpha.1');
        $v6 = new Version('2.0.0-beta.1');
        $v7 = new Version('2.0.0-beta.2');
        $v8 = new Version('2.0.0');
        $v9 = new Version('2.0.1-alpha.1+build.143');
        $v10 = new Version('2.0.1-alpha.1+build.155');

        // Comparisons
        $this->assertTrue($v1->equalTo($v2));
        $this->assertFalse($v1->equalTo($v3));
        $this->assertTrue($v1->lessThan($v4));
        $this->assertTrue($v1->lessThan($v5));
        $this->assertTrue($v1->lessThan($v6));
        $this->assertTrue($v6->greaterThan($v5)); // beta > alpha
        $this->assertTrue($v7->greaterThan($v6)); // Release is greater than pre-release
        $this->assertTrue($v6->greaterThan($v1));
        $this->assertTrue($v5->greaterThan($v4));

        $this->assertEquals(0, $v1->compareTo($v2));
        $this->assertEquals(-1, $v1->compareTo($v4));
        $this->assertEquals(1, $v4->compareTo($v1));
        $this->assertEquals(-1, $v1->compareTo($v3));
        $this->assertEquals(1, $v3->compareTo($v1));
        $this->assertEquals(-1, $v5->compareTo($v6)); // alpha < beta
        $this->assertEquals(1, $v6->compareTo($v5)); // beta > alpha
        $this->assertEquals(-1, $v7->compareTo($v8)); // pre-release < release
        $this->assertEquals(1, $v8->compareTo($v7)); // release > pre-release

        $this->assertTrue($v6->lessThan($v7)); // bool(true) because beta.1 < beta.2
        $this->assertTrue($v7->lessThan($v8)); // bool(true) because pre-release < normal release
        $this->assertTrue($v6->equals('2.0.0-beta.1+build.999')); // bool(true) - metadata ignored for equality

        // Metadata should not affect comparison
        $this->assertEquals(0, $v9->compareTo($v10)); // Same version, different metadata
        $this->assertTrue($v9->equalTo($v10)); // Same version, different metadata
    }

    public function testCreateClosureFromClosure(): void
    {
        $closure = new Closure(function (string $myValue): string {
            return $myValue;
        });
        $this->assertStringStartsWith('function (string $myValue): string', $closure->getCode());
        $this->assertCount(1, $closure->getParameters());
        $this->assertEquals('myValue', $closure->getParameters()[0]->getName());
        $this->assertEquals('Hello, World', $closure('Hello, World'));
    }

    public function testCreateClosureFromArrowFunction(): void
    {
        $closure = new Closure(fn ($myValue) => ($myValue).('!'));
        $this->assertStringStartsWith('fn ($myValue) => ($myValue).(\'!\')', $closure->getCode());
        $this->assertCount(1, $closure->getParameters());
        $this->assertEquals('myValue', $closure->getParameters()[0]->getName());
        $this->assertEquals('Hello, World!', $closure('Hello, World'));
    }

    public function testCanRunClosureAfterSerialization(): void
    {
        $closure = new Closure(function (string $myValue): string {
            if ('Hello, World' !== $myValue) {
                throw new \InvalidArgumentException('Expected a string');
            }

            return $myValue;
        });
        $serialized = serialize($closure);
        $unserializedClosure = unserialize($serialized);
        $this->assertInstanceOf(Closure::class, $unserializedClosure);
        $this->assertEquals('Hello, World', $unserializedClosure('Hello, World'));
    }

    public function testCanRunClosureAfterJSONSerialization(): void
    {
        $closure = new Closure(function (string $myValue): string {
            if ('Hello, World' !== $myValue) {
                throw new \InvalidArgumentException('Expected a string');
            }

            return $myValue;
        });
        $json = json_encode($closure);
        $unserializedClosure = new Closure(json_decode($json));
        $this->assertInstanceOf(Closure::class, $unserializedClosure);
        $this->assertEquals('Hello, World', $unserializedClosure('Hello, World'));
    }

    public function testClosureSerializationThrowsExceptionOnInvalidClosure(): void
    {
        $this->expectException(InvalidClosure::class);
        $test = 'Hello, World';
        $closure = new Closure(function () use ($test): string {
            return $test;
        });
        // This will throw an error because the closure cannot be serialized when the "use" keyword is used
        $serialized = serialize($closure);
    }
}
