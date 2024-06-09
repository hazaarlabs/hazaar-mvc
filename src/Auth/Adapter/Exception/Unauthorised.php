<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter\Exception;

class Unauthorised extends \Exception
{
    public function __construct(bool $basic = false)
    {
        if (true === $basic) {
            header('WWW-Authenticate: Basic');
        }
        parent::__construct('Unauthorised', 401);
    }
}
