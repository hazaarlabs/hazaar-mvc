<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class RouterInitialisationFailed extends \Exception
{
    public function __construct(string $msg)
    {
        parent::__construct('Failed to initialise router: '.$msg, 500);
    }
}
