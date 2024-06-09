<?php

declare(strict_types=1);

namespace Hazaar\Controller\Action\Exception;

class InvalidActionController extends \Exception
{
    public function __construct(string $controller)
    {
        parent::__construct('These are called ACTION helpers for a reason.  ie: they will not work with '.$controller);
    }
}
