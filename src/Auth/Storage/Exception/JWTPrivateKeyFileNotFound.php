<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class JWTPrivateKeyFileNotFound extends \Exception
{
    public function __construct(string $keyFile)
    {
        parent::__construct("The private key file '{$keyFile}' could not be found!");
    }
}
