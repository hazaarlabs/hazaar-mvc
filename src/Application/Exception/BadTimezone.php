<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class BadTimezone extends Exception
{
    public function __construct(string $tz)
    {
        parent::__construct("The timezone from application.ini is invalid. <a href=\"http://www.php.net/manual/en/timezones.php\">See here for valid timezones</a>. ({$tz})");
    }
}
