<?php

declare(strict_types=1);

namespace Hazaar\File\Exception\WKPDF;

use Hazaar\Exception;

class ExecError extends Exception
{
    public function __construct(int|string $code)
    {
        parent::__construct('WKPDF shell error, return code '.$code.'.');
    }
}
