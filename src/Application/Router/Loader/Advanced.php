<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Request;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Loader;
use Hazaar\Controller;

class Advanced extends Loader
{
    public function exec(Request $request): bool
    {
        $path = $request->getPath();
        if (empty($path) || '/' === $path) {
            return true;
        }
        if ($this->config->has('aliases')) {
            $path = $this->evaluateAliases($path, $this->config['aliases']->toArray());
        }
        $parts = [];
        $controller = $this->findController($path, $parts);
        $action = 'index';
        if (null === $controller) {
            $slashPos = strpos($path, '/');
            $action = false === $slashPos ? $path : substr($action, 0, $slashPos);

            return false;
        }
        $controller = ucfirst($controller);
        if (count($parts) > 0) {
            $action = array_shift($parts);
        }
        $actionArgs = $parts;
        Router::default([$controller, $action, $actionArgs]);

        return true;
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
        $controllerParts = explode('/', $path);
        $controller = null;
        $controllerRoot = Loader::getFilePath(FILE_PATH_CONTROLLER);
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
