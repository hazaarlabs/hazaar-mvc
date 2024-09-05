<?php

namespace Hazaar\Application;

use Hazaar\Controller;
use Hazaar\Controller\Closure;

class Route
{
    private Router $router;
    private mixed $callable;
    private ?string $path = null;

    /**
     * @var array<string>
     */
    private array $methods = [];

    /**
     * @var array<mixed>
     */
    private array $actionArgs = [];

    /**
     * @param array<string> $methods
     */
    public function __construct(mixed $callable, ?string $path = null, array $methods = [])
    {
        $this->callable = $callable;
        $this->path = $path;
        $this->methods = array_map('strtoupper', $methods);
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    public function match(string $method, string $path): bool
    {
        if (!in_array($method, $this->methods)) {
            return false;
        }
        $path = explode('/', $path);
        $routePath = explode('/', trim($this->path, '/'));
        if (count($routePath) !== count($path)) {
            return false;
        }
        foreach ($routePath as $i => &$part) {
            if ($part === $path[$i]) {
                continue;
            }
            if ('{' === substr($part, 0, 1) && '}' === substr($part, -1)) {
                $this->actionArgs[] = $path[$i];

                continue;
            }

            return false;
        }

        return true;
    }

    public function getController(): ?Controller
    {
        $controller = null;
        if ($this->callable instanceof \Closure) {
            return new Closure($this->router, $this->callable);
        }

        if (is_callable($this->callable)) {
            dump($this->callable);
        } elseif (is_array($this->callable)) {
            if (!class_exists($this->callable[0])) {
                throw new Router\Exception\ControllerNotFound($this->callable[0], $this->path);
            }
            $controller = new $this->callable[0]($this->router);
        }

        return $controller;
    }

    public function getAction(): string
    {
        return isset($this->callable[1]) ? $this->callable[1] : 'index';
    }

    /**
     * @return array<mixed>
     */
    public function getActionArgs(): array
    {
        return $this->actionArgs;
    }
}
