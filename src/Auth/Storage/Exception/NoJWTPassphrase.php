<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class NoJWTPassphrase extends \Exception
{
    public function __construct()
    {
        parent::__construct('No passphrase set for JWT signing', 500);
    }
}
