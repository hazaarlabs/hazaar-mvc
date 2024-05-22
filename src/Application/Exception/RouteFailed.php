<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class RouteFailed extends Exception
{
    public function __construct()
    {
        parent::__construct('Failed to process routing information!');
    }
}
