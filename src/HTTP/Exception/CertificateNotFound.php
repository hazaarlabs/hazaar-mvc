<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

use Hazaar\Exception;

class CertificateNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct('File not found while trying to load local client certificate');
    }
}
