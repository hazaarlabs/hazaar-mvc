<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

/**
 * Exception thrown when a configuration file is not found.
 *
 * This exception is used to indicate that a required configuration file
 * does not exist in the specified location.
 */
class ConfigFileNotFound extends \Exception
{
    /**
     * Constructor for ConfigFileNotFound exception.
     *
     * @param string $fileName the name of the configuration file that was not found
     */
    public function __construct(string $fileName)
    {
        parent::__construct("The configuration file '{$fileName}' does not exist.", 503);
    }
}
