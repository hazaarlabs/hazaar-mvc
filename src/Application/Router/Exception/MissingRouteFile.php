<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class MissingRouteFile extends \Exception
{
    public function __construct(string $file)
    {
        parent::__construct("Custom router file '{$file}' not found");
    }
}
