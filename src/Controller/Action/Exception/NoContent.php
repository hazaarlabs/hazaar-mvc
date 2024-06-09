<?php

declare(strict_types=1);

namespace Hazaar\Controller\Action\Exception;

class NoContent extends \Exception
{
    public function __construct(string $class)
    {
        parent::__construct('The view renderer did not produce any content while rendering '.$class);
    }
}
