<?php

namespace Hazaar\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;

class MiddlewareDispatcher
{
    /**
     * @var array<Middleware>
     */
    private array $middlewareStack = [];

    public function add(Middleware $middleware): void
    {
        $this->middlewareStack[] = $middleware;
    }

    public function loadMiddleware(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("Directory {$directory} does not exist.");
        }

        foreach (glob($directory.'/*.php') as $file) {
            $middlewareClass = 'App\Middleware\\'.basename($file, '.php');
            if (!class_exists($middlewareClass)) {
                continue;
            }
            if (!is_subclass_of($middlewareClass, Middleware::class)) {
                throw new \InvalidArgumentException("Class {$middlewareClass} does not implement Middleware interface.");
            }
            $this->add(new $middlewareClass());
        }
    }

    public function handle(Request $request, callable $finalHandler): Response
    {
        $stack = array_reverse($this->middlewareStack);
        $next = $finalHandler;
        foreach ($stack as $middleware) {
            $next = function (Request $request) use ($middleware, $next) {
                return $middleware->handle($request, $next);
            };
        }

        // Call the final next function which should be the controller or last middleware
        return $next($request);
    }
}
