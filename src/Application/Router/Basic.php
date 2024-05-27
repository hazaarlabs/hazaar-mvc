<?php

declare(strict_types=1);

namespace Hazaar\Application\Router;

use Hazaar\Application\Request;
use Hazaar\Application\Router;

/**
 * Basic Application Router.
 *
 * This router is a basic router that will evaluate the request path and set the
 * controller, action, and arguments from a simple formatted path.
 *
 * A path should be formatted as /controller/action/arg1/arg2/arg3
 *
 * This will set the controller to 'Controller', the action to 'action', and the
 * arguments to ['arg1', 'arg2', 'arg3'] which will result in the
 * \Application\Controllers\Controller::action('arg1', 'arg2', 'arg3') method being called.
 */
class Basic extends Router
{
    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @param Request $request the request object
     *
     * @return bool returns true if the evaluation is successful, false otherwise
     */
    public function evaluateRequest(Request $request): bool
    {
        $path = $request->getPath();
        $parts = explode('/', $path);
        if (isset($parts[0]) && '' !== $parts[0]) {
            $this->controller = ucfirst($parts[0]);
        }
        if (isset($parts[1]) && '' !== $parts[1]) {
            $this->action = $parts[1];
        }
        if (count($parts) > 2) {
            $this->actionArgs = array_slice($parts, 2);
        }

        return true;
    }
}
