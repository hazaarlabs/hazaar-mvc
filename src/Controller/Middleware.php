<?php

namespace Hazaar\Controller;

class Middleware
{
    /**
     * Name of the middleware.
     */
    public string $name;

    /**
     * Array of methods this middleware applies to.
     *
     * @var array<string>
     */
    private array $methods = [];

    /**
     * Array of methods this middleware does not apply to.
     *
     * @var array<string>
     */
    private array $exceptMethods = [];

    public function __construct(string $name)
    {
        // Initialize middleware with the given name
        $this->name = $name;
    }

    public function only(string $method): self
    {
        // Set the methods that this middleware should apply to
        $this->methods[] = $method;

        return $this;
    }

    public function except(string $method): self
    {
        // Set the methods that this middleware should not apply to
        $this->exceptMethods[] = $method;

        return $this;
    }

    public function match(string $actionName): bool
    {
        if (count($this->methods) > 0) {
            return in_array($actionName, $this->methods) && !in_array($actionName, $this->exceptMethods);
        }

        return !in_array($actionName, $this->exceptMethods);
    }
}
