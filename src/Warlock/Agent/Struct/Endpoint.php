<?php

namespace Hazaar\Warlock\Agent\Struct;

use Hazaar\Warlock\Protocol;

class Endpoint
{
    /**
     * @var object|string
     */
    public $target;

    public string $method;

    /**
     * @var array<mixed>
     */
    public array $params = [];

    /**
     * @param string       $method The method name to call on the callable
     * @param mixed        $target
     * @param array<mixed> $params Parameters to pass to the method
     */
    public function __construct($target, string $method, array $params = [])
    {
        $this->target = $target;
        $this->method = $method;
        $this->params = $params;
    }

    public static function create(mixed $value): ?self
    {
        if ($value instanceof \stdClass) { // Probably a payload from a packet
            return new self(
                $value->target ?? '',
                $value->method ?? '',
                $value->params ?? []
            );
        }
        if (!is_array($value)) {
            if (false !== strpos($value, '::')) {
                $value = explode('::', $value, 2);
            } elseif (false !== strpos($value, '->')) {
                $value = explode('->', $value, 2);
            }
        }
        if (is_array($value)) {
            return new self(
                $value[0],
                $value[1],
                $value[2] ?? []
            );
        }

        return null;
    }

    public function getName(): string
    {
        if (is_object($this->target)) {
            return get_class($this->target).'::'.$this->method;
        }

        return $this->target.'::'.$this->method;
    }

    public function run(Protocol $protocol): void
    {
        if (is_object($this->target)) {
            call_user_func_array([$this->target, $this->method], $this->params);

            return;
        }
        $reflectionClass = new \ReflectionClass($this->target);
        if (false === $reflectionClass->hasMethod($this->method)) {
            throw new \RuntimeException(sprintf(
                'Method %s::%s does not exist.',
                $this->target,
                $this->method
            ));
        }
        $methodReflection = $reflectionClass->getMethod($this->method);
        if (!$methodReflection->isPublic()) {
            throw new \RuntimeException(sprintf(
                'Method %s::%s is not public.',
                $this->target,
                $this->method
            ));
        }
        if ($reflectionClass->isSubclassOf('\Hazaar\Warlock\Agent\Container')) {
            $container = $reflectionClass->newInstance($protocol);
            $methodReflection->invoke($container, ...$this->params);

            return;
        }
        if (!$methodReflection->isStatic()) {
            throw new \RuntimeException(sprintf(
                'Method %s::%s is not static.',
                $this->target,
                $this->method
            ));
        }
    }

    /**
     * Converts the Endpoint to an array representation.
     *
     * @return array{target:string,method:string,params:array<string,mixed>} The array representation of the Endpoint
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'method' => $this->method,
            'params' => $this->params,
        ];
    }
}
