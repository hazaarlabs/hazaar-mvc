<?php

declare(strict_types=1);

use Hazaar\Warlock\Control;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class WarlockTest extends TestCase
{
    public function testCanCastFireball(): void
    {
        $warlock = new Control();
        $this->assertTrue($warlock->connect());
    }
}
