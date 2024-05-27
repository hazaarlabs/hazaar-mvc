<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class NotSupported extends \Exception
{
    public function __construct()
    {
        parent::__construct('The configured router only supports HTTP requests');
    }
}
