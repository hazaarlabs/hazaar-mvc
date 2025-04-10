<?php

declare(strict_types=1);

namespace Hazaar\Controller;

use Hazaar\Application;
use Hazaar\Application\FilePath;
use Hazaar\Application\Route;
use Hazaar\Controller;
use Hazaar\Controller\Response\File;

/**
 * Class Internal.
 *
 * This class extends the base Controller class and provides methods to execute actions associated with routes
 * and handle internal controller actions.
 */
class Internal extends Controller
{
    /**
     * Executes the action associated with the given route and returns the response.
     *
     * @param null|Route $route the route containing the action and its arguments
     *
     * @return Response the response generated by the action or a file response if the action does not return a Response instance
     *
     * @throws \Exception if the support file specified by the route is not found
     */
    public function runRoute(?Route $route = null): Response
    {
        $response = $this->runAction($route->getAction(), $route->getActionArgs());
        if ($response instanceof Response) {
            return $response;
        }
        $filename = $route->getPath();
        $app = Application::getInstance();
        $file = $app->loader->getFilePath(FilePath::SUPPORT, $filename);
        if (null === $file) {
            throw new \Exception("Hazaar support file '{$filename}' not found!", 404);
        }

        return new File($file);
    }

    /**
     * Executes an internal controller action based on the provided action name.
     *
     * @param string       $actionName      the name of the action to execute, in the format 'route/action'
     * @param array<mixed> $actionArgs      Optional. An array of arguments to pass to the action. Default is an empty array.
     * @param bool         $namedActionArgs Optional. Whether the action arguments are named. Default is false.
     *
     * @return false|Response returns a Response object if the action is successfully executed, or false if the action or controller is not found
     *
     * @throws \Exception if the internal controller action is not found, an exception is thrown with a 404 status code
     */
    public function runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response
    {
        if (!($offset = strpos($actionName, '/'))) {
            return false;
        }
        $route = substr($actionName, 0, $offset);
        $internalClassName = '\Hazaar\Controller\Internal\\'.ucfirst($route);
        if (!class_exists($internalClassName)) {
            return false;
        }
        $controller = new $internalClassName($route);
        $response = $controller->runAction(substr($actionName, $offset + 1), $actionArgs, $namedActionArgs);
        if (!$response) {
            throw new \Exception("Internal controller action '{$actionName}' not found!", 404);
        }

        return $response;
    }
}
