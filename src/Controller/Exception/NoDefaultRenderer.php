<?php

namespace Hazaar\Controller\Exception;

use Hazaar\Exception;

class NoDefaultRenderer extends Exception
{
    public function __construct()
    {
        parent::__construct('Could not load default view renderer!', 500);
    }
}
