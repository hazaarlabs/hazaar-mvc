<?php

namespace HazaarTest;

class MoneyTest extends \PHPUnit_Framework_TestCase {

    public function testCanDoAdd() {

        $a = new \Hazaar\Money(100, 'USD');

        $b = new \Hazaar\Money(100, 'AUD');

        $a->add($b);

        $this->assertInternalType('float', $a->toFloat());

    }

    public function testCanGetExchangeRate() {

        $a = new \Hazaar\Money(100, 'AUD');

        $rate = $a->getExchangeRate('USD');

        $this->assertInternalType('float', $rate);

        $this->assertGreaterThan(0, $rate);

    }

    public function testCanConvertTo() {

        $a = new \Hazaar\Money(100, 'USD');

        $a->convertTo('AUD');

        $this->assertInternalType('string', $a->toString());

    }

}