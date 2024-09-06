<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class ProtocolNotSupported extends \Exception
{
    public function __construct()
    {
        parent::__construct('The configured route only supports HTTP requests');
    }
}
