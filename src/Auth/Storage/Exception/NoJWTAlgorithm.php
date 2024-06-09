<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class NoJWTAlgorithm extends \Exception
{
    public function __construct()
    {
        parent::__construct('No algorithm set for JWT signing', 500);
    }
}
