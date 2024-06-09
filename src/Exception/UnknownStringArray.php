<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class UnknownStringArray extends \Exception
{
    public function __construct(string $defaults)
    {
        parent::__construct('Unknown string array format! Got: "'.$defaults.'"');
    }
}
