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
class NoSendmail extends Exception
{
    public function __construct()
    {
        parent::__construct('Sendmail is not available on this system', 500);
    }
}
