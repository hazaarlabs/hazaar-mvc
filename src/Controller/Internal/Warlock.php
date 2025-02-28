<?php

declare(strict_types=1);

namespace Hazaar\Controller\Internal;

use Hazaar\Controller;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\Text;
use Hazaar\Warlock\Config;

/**
 * Class Warlock.
 *
 * This class extends the Controller and provides methods to execute specified actions and retrieve system information.
 */
class Warlock extends Controller
{
    /**
     * Executes a specified action method within the controller.
     *
     * @param string       $actionName      the name of the action method to execute
     * @param array<mixed> $actionArgs      Optional. An array of arguments to pass to the action method. Default is an empty array.
     * @param bool         $namedActionArgs Optional. A flag indicating whether the action arguments are named. Default is false.
     *
     * @return false|Response returns false if the action method does not exist, otherwise returns the result of the action method
     */
    public function runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response
    {
        if (!method_exists($this, $actionName)) {
            return false;
        }

        return $this->{$actionName}(...$actionArgs);
    }

    /**
     * Retrieves the system ID from the configuration and returns it as a text response.
     *
     * @return Response the system ID wrapped in a Text response object
     */
    private function sid(): Response
    {
        $config = new Config();

        return new Text($config['sys']['id'] ?? '');
    }
}
