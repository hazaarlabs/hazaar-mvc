<?php

declare(strict_types=1);

namespace Hazaar\Cache\Exception;

class InvalidFrontend extends \Exception
{
    public function __construct(string $class)
    {
        parent::__construct("Object of class '{$class}' is not a valid cache frontend!");
    }
}
