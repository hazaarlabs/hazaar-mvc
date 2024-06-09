<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class ActionNotPublic extends \Exception
{
    public function __construct(string $controller, string $action)
    {
        parent::__construct("Target action {$controller}::{$action}() is not public!", 403);
    }
}
