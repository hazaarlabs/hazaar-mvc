<?php

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Exception\ControllerHasNoRoutes;

class Attribute extends Advanced
{
    /**
     * Initialises the basic router.
     *
     * @return bool returns true if the initialisation is successful, false otherwise
     */
    public function initialise(Router $router): bool
    {
        return true;
    }

    public function evaluateRequest(Request $request): ?Route
    {
        $route = parent::evaluateRequest($request);
        if (!$route) {
            return null;
        }
        $controller = $route->getControllerName();
        $controllerEndpoints = $this->loadEndpoints($route->getControllerName(), $route->getControllerClass());
        if (0 === count($controllerEndpoints)) {
            throw new ControllerHasNoRoutes($controller);
        }
        foreach ($controllerEndpoints as $endpoint) {
            Router::add($endpoint);
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    private function loadEndpoints(string $controllerName, string $controllerClass): array
    {
        $endpoints = [];
        $controllerReflection = new \ReflectionClass($controllerClass);
        foreach ($controllerReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);
            foreach ($attributes as $attribute) {
                $endpoint = $attribute->newInstance();
                $endpoint->setCallable([$controllerClass, $method->getName()]);
                $endpoint->prefixPath($controllerName);
                $endpoints[] = $endpoint;
            }
        }

        return $endpoints;
    }
}
