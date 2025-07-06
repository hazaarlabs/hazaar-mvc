<?php

namespace Hazaar\Util\Exception;

class ClosureNotInitialised extends \Exception
{
    public function __construct(string $message = 'Closure not initialised.')
    {
        parent::__construct($message);
    }
}
