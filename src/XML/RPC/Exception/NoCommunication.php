<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

use Hazaar\HTTP\URL;

class NoCommunication extends \Exception
{
    public function __construct(URL $url)
    {
        parent::__construct('Error communicating with '.$url.'.  Check the address and try again.');
    }
}
