<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class NoJWTPrivateKey extends \Exception
{
    public function __construct()
    {
        parent::__construct('No private key set for JWT signing', 500);
    }
}
