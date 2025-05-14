<?php

declare(strict_types=1);

namespace Hazaar\Exception;

/**
 * Exception thrown when a required configuration environment is not found.
 */
class ConfigEnvNotFound extends \Exception
{
    /**
     * Constructor for ConfigEnvNotFound exception.
     *
     * @param string $env the name of the configuration environment that was not found
     */
    public function __construct(string $env)
    {
        parent::__construct("The required configuration environment '{$env}' does not exist.", 503);
    }
}
