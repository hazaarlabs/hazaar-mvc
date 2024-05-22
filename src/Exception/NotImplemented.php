<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class NotImplemented extends Exception
{
    public function __construct(?string $module = null)
    {
        $msg = 'Not implemented';
        if ($module) {
            $msg = $module.' is not implemented';
        }
        parent::__construct($msg);
    }
}
