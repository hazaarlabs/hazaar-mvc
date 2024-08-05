<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

class DecryptFailed extends \Exception
{
    public function __construct(string $errString)
    {
        parent::__construct('Failed to decrypt response body: '.$errString);
    }
}
