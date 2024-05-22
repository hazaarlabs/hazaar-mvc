<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\Exception;

use Hazaar\Exception;

class JSONNotSupported extends Exception
{
    public function __construct()
    {
        parent::__construct('The json_encode() PHP function was not found.  Make sure that your PHP installation has JSON support.', 500);
    }
}
