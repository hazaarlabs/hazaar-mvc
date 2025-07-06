<?php

namespace Hazaar\Util\Exception;

class InvalidClosure extends \Exception
{
    public function __construct(string $message = 'Invalid closure provided.')
    {
        parent::__construct($message);
    }
}
