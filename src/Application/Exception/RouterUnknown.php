<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class RouterUnknown extends Exception
{
    public function __construct(string $routerType)
    {
        parent::__construct("Router type '{$routerType}' is unknown.", 503);
    }
}
