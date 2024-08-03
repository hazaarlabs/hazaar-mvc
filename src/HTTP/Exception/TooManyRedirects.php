<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

class TooManyRedirects extends \Exception
{
    public function __construct()
    {
        parent::__construct('Request is not redirecting correctly.  Too many redirect attempts.');
    }
}
