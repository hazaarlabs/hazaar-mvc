<?php

namespace Hazaar\Application\Exception;

class BadTimezone extends \Hazaar\Exception {

    function __construct($tz) {

        parent::__construct("The timezone from application.ini is invalid. <a href=\"http://www.php.net/manual/en/timezones.php\">See here for valid timezones</a>. ($tz)");

    }

}
