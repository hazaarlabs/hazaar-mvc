<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

/**
 * Exception thrown when a configuration file has an unknown or unsupported format.
 */
class ConfigUnknownFormat extends \Exception
{
    /**
     * Constructs a new ConfigUnknownFormat exception.
     *
     * @param string $fileName the name of the configuration file with the unknown format
     */
    public function __construct(string $fileName)
    {
        parent::__construct("The configuration '{$fileName}' is in an unknown format.", 503);
    }
}
