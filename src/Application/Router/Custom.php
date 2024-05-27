<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Router.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application\Router;

use Hazaar\Application\Request;
use Hazaar\Application\Router;

class Custom extends Router
{
    /**
     * @var array<string,array<string,mixed>>
     */
    public array $routes = [];

    public function evaluateRequest(Request $request): bool
    {
        $filename = $this->config->get('file', 'route.php');
        $file = APPLICATION_PATH.DIRECTORY_SEPARATOR.$filename;
        if (false === file_exists($file)) {
            throw new Exception\MissingRouteFile($filename);
        }
        $route = $this;

        include_once $file;

        if (!$request instanceof Request\HTTP) {
            throw new Exception\NotSupported();
        }
        $method = $request->method();
        if (!array_key_exists($method, $this->routes)) {
            return false;
        }
        $path = explode('/', $request->getPath());
        $routes = $route->routes[$method];
        $match = false;
        foreach ($routes as $route => $callback) {
            $route = explode('/', trim($route, '/'));
            if (count($route) !== count($path)) {
                continue;
            }
            $args = [];
            foreach ($route as $i => &$part) {
                if ($part === $path[$i]) {
                    continue;
                }
                if ('{' === substr($part, 0, 1) && '}' === substr($part, -1)) {
                    $args[] = $path[$i];

                    continue;
                }

                continue 2;
            }
            if ('Application\\Controllers\\' === substr($callback[0], 0, 24)) {
                $this->controller = substr($callback[0], 24);
            } else {
                $this->controller = '\\'.$callback[0];
            }
            if (isset($callback[1])) {
                $this->action = $callback[1];
            }
            $this->actionArgs = $args;
            $match = true;

            break;
        }

        return $match;
    }

    public function get(string $path, mixed $callback): void
    {
        $this->match('GET', $path, $callback);
    }

    public function post(string $path, mixed $callback): void
    {
        $this->match('POST', $path, $callback);
    }

    public function put(string $path, mixed $callback): void
    {
        $this->match('PUT', $path, $callback);
    }

    public function delete(string $path, mixed $callback): void
    {
        $this->match('DELETE', $path, $callback);
    }

    public function patch(string $path, mixed $callback): void
    {
        $this->match('PATCH', $path, $callback);
    }

    public function options(string $path, mixed $callback): void
    {
        $this->match('OPTIONS', $path, $callback);
    }

    public function any(string $path, mixed $callback): void
    {
        $this->match('ANY', $path, $callback);
    }

    private function match(string $method, string $path, mixed $callback): void
    {
        if (!class_exists($callback[0])) {
            return;
        }
        $this->routes[$method][$path] = $callback;
    }
}
