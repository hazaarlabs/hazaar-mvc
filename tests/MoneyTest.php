<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MoneyTest extends TestCase
{
    public function testCanAdd(): void
    {
        $a = new Money(100, 'USD');
        $b = new Money(100, 'AUD');
        $this->assertEquals((float)100, $a->toFloat());
        $this->assertEquals((float)100,$b->toFloat());
        $c = $a->add($b);
        $this->assertGreaterThan(100, $c->toFloat());
    }

    public function testCanGetExchangeRate(): void
    {
        $a = new Money(100, 'AUD');
        $rate = $a->getExchangeRate('USD');
        $this->assertGreaterThan(0, $rate);
    }

}
