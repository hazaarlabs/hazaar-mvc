<?php

declare(strict_types=1);

namespace Hazaar\File\Exception\WKPDF;

use Hazaar\Exception;

class NotExecutable extends Exception
{
    public function __construct(string $cmd)
    {
        parent::__construct('WKPDF static executable "'.htmlspecialchars($cmd, ENT_QUOTES).'" is not executable.');
    }
}
