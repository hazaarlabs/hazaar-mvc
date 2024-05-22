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
        $this->assertIsFloat($a->toFloat());
        $this->assertIsFloat($b->toFloat());
        $c = $a->add($b);
        $this->assertIsFloat($c->toFloat());
    }

    public function testCanGetExchangeRate(): void
    {
        $a = new Money(100, 'AUD');
        $rate = $a->getExchangeRate('USD');
        $this->assertIsFloat($rate);
        $this->assertGreaterThan(0, $rate);
    }

    public function testCanConvertTo(): void
    {
        $a = new Money(100, 'USD');
        $a->convertTo('AUD');
        $this->assertIsString($a->toString());
    }
}
