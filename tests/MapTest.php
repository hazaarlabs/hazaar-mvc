<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Map;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MapTest extends TestCase
{
    private Map $map;

    public function setUp(): void
    {
        $this->map = new Map([
            'value' => 'test',
            'array' => [1, 2, 3],
        ]);
    }

    public function testBasicMap(): void
    {
        $this->assertCount(2, $this->map->toArray());
        $this->assertEquals('test', $this->map['value']);
    }

    public function testMapFilter(): void
    {
        $this->map->filter(function ($value) {
            return 'test' === $value;
        });
        $this->assertContains('test', $this->map->toArray());
        $this->assertNotContains('array', $this->map->toArray());
    }
}
