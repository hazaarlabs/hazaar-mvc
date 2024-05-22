<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response\Exception;

use Hazaar\Exception;

class NoRenderer extends Exception
{
    public function __construct(string $renderer)
    {
        parent::__construct("A renderer could not be found for type '{$renderer}'");
    }
}
