<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

/**
 * Exception thrown when a configuration file cannot be parsed due to invalid format.
 */
class ConfigParseError extends \Exception
{
    /**
     * Constructs a new ConfigParseError exception.
     *
     * @param string $fileName the name of the configuration file that could not be parsed
     */
    public function __construct(string $fileName)
    {
        parent::__construct("The configuration '{$fileName}' is in an invalid format.", 503);
    }
}
