<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class HeadersSent extends \Exception
{
    public function __construct()
    {
        parent::__construct('Headers already sent while trying to render controller response.  Make sure your response uses $this->setHeader().');
    }
}
