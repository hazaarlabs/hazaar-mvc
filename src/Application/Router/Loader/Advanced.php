<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\FilePath;
use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Loader;
use Hazaar\Controller;

class Advanced extends Loader
{
    /**
     * @var array<mixed>
     */
    protected array $actionArgs;

    /**
     * Initialises the advanced router.
     *
     * @return bool returns true if the initialisation is successful, false otherwise
     */
    public function initialise(Router $router): bool
    {
        return true;
    }

    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @param Request $request the request object
     *
     * @return Route returns the route object if the evaluation is successful, null otherwise
     */
    public function evaluateRequest(Request $request): ?Route
    {
        $path = $request->getPath();
        if ('/' === $path) {
            return null;
        }
        if (isset($this->config['aliases'])) {
            $path = $this->evaluateAliases($path, $this->config['aliases']);
            $request->setPath($path);
        }
        $parts = [];
        $controller = trim($this->findController($path, $parts) ?? '', '\\');
        if ('' === $controller) {
            return null;
        }
        $controllerClass = 'Application\Controller\\'.$controller;
        $action = (count($parts) > 0) ? array_shift($parts) : null;
        $actionArgs = $parts;
        $route = new Route($path);
        $route->setCallable([$controllerClass, $action, $actionArgs]);

        return $route;
    }

    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @param string               $route   the request path
     * @param array<string,string> $aliases the aliases
     *
     * @return string the evaluated path
     */
    private function evaluateAliases(string $route, array $aliases): string
    {
        $route = ltrim($route, '/');
        foreach ($aliases as $match => $alias) {
            if (substr($route, 0, strlen($match)) !== $match) {
                continue;
            }

            return $alias.substr($route, strlen($match));
        }

        return $route;
    }

    /**
     * Finds the controller based on the given parts.
     *
     * @param string        $path            the name of the controller
     * @param array<string> $controllerParts
     *
     * @return string the name of the controller
     */
    private function findController(string $path, array &$controllerParts): ?string
    {
        $controllerParts = explode('/', ltrim($path, '/'));
        $controller = null;
        $controllerRoot = \Hazaar\Loader::getFilePath(FilePath::CONTROLLER);
        $controllerPath = DIRECTORY_SEPARATOR;
        $controllerIndex = null;
        $defaultController = ucfirst($this->config['controller']);
        foreach ($controllerParts as $index => $part) {
            $part = ucfirst($part);
            $found = false;
            $searchPath = $controllerRoot.$controllerPath;
            $controllerPath .= $part.DIRECTORY_SEPARATOR;
            if (is_dir($searchPath.$part)) {
                $found = true;
                if (file_exists($controllerRoot.$controllerPath.$defaultController.'.php')) {
                    $controller = implode('\\', array_map('ucfirst', array_slice($controllerParts, 0, $index + 1))).'\\'.$defaultController;
                    $controllerIndex = $index;
                }
            }
            if (file_exists($searchPath.$part.'.php')) {
                $found = true;
                $controller = implode('\\', array_map('ucfirst', array_slice($controllerParts, 0, $index + 1)));
                $controllerIndex = $index;
            }
            if (false === $found) {
                break;
            }
        }
        if ($controller) {
            $controllerParts = array_slice($controllerParts, $controllerIndex + 1);
        }

        return $controller;
    }
}
