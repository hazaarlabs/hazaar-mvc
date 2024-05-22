<?php

declare(strict_types=1);

namespace Hazaar\File\Exception\WKPDF;

use Hazaar\Exception;

class InstallFailed extends Exception
{
    public function __construct(string $cmd, string $error)
    {
        $msg = 'WKPDF static executable "'.htmlspecialchars($cmd, ENT_QUOTES).'" was not found so an automated installation was attempted but failed.';

        $msg .= '<p><b>Reason:</b> '.$error;

        parent::__construct($msg);
    }
}
