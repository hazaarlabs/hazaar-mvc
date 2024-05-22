<?php

namespace Hazaar\Tests;

use Hazaar\Cron;
use Hazaar\Exception;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CronTest extends TestCase
{
    public function testCronMidnight(): void
    {
        $cron = new Cron('0 0 1 1 *');
        $this->assertIsInt($cron->getNextOccurrence());
        $this->assertGreaterThan(time(), $cron->getNextOccurrence());
    }

    public function testCronExecEveryMinute(): void
    {
        $cron = new Cron('* * * * *');
        $this->assertIsInt($cron->getNextOccurrence());
        $this->assertGreaterThan(time(), $cron->getNextOccurrence());
    }

    public function testCronBadTime(): void
    {
        $this->expectException(Exception::class);
        $cron = new Cron('0 0 1 1');
    }
}
