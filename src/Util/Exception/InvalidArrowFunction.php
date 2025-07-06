<?php

namespace Hazaar\Util\Exception;

class InvalidArrowFunction extends \Exception
{
    public function __construct(string $message = 'Invalid arrow function provided.')
    {
        parent::__construct($message);
    }
}
