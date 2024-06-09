<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class UnsupportedJWTAlgorithm extends \Exception
{
    public function __construct(string $algorithm)
    {
        parent::__construct('Unsupported JWT algorithm: '.$algorithm, 500);
    }
}
