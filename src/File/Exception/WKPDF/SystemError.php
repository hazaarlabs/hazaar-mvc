<?php

declare(strict_types=1);

namespace Hazaar\File\Exception\WKPDF;

use Hazaar\Exception;

class SystemError extends \Exception
{
    public function __construct(string $error)
    {
        parent::__construct('WKPDF system error: <pre>'.$error.'</pre>');
    }
}
