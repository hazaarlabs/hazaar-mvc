<?php

namespace Hazaar\Mail\Transport\Exception;

use Hazaar\Exception;

/**
 * FailConnect short summary.
 *
 * FailConnect description.
 *
 * @version 1.0
 *
 * @author jamiec
 */
class FailConnect extends Exception
{
    public function __construct(string $message, int $type)
    {
        parent::__construct("Mail Transport Error #{$type}: {$message}");
    }
}
