<?php

declare(strict_types=1);

namespace Hazaar\Controller\Internal;

use Hazaar\Controller;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\Text;
use Hazaar\Warlock\Config;

class Warlock extends Controller
{
    public function __runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response
    {
        if (!method_exists($this, $actionName)) {
            return false;
        }

        return $this->{$actionName}(...$actionArgs);
    }

    private function sid(): Response
    {
        $config = new Config();

        return new Text($config->sys['id']);
    }
}
