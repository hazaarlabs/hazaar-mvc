<?php

declare(strict_types=1);

namespace Hazaar\File\Exception\WKPDF;

use Hazaar\Exception;

class NoData extends \Exception
{
    public function __construct(string $error)
    {
        parent::__construct('WKPDF didn\'t return any data. <pre>'.$error.'</pre>');
    }
}
