<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class ConfigEnvNotFound extends Exception
{
    public function __construct(string $env)
    {
        parent::__construct("The required configuration environment '{$env}' does not exist.");
    }
}
