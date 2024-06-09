<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\Exception;

class NoLessSupport extends \Exception
{
    public function __construct()
    {
        parent::__construct('Less CSS files are not currently supported!  Please install leafo/lessphp with Composer.');
    }
}
