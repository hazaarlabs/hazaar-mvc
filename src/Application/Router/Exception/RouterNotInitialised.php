<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class RouterNotInitialised extends \Exception
{
    public function __construct()
    {
        parent::__construct('Router instance is not initialised.', 500);
    }
}
