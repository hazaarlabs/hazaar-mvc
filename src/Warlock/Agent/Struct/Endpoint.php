<?php

namespace Hazaar\Warlock\Agent\Struct;

use Hazaar\Util\Closure;
use Hazaar\Warlock\Protocol;

class Endpoint implements \JsonSerializable
{
    /**
     * @var object|string
     */
    private $target;

    private string $method;

    /**
     * @var array<mixed>
     */
    private array $params = [];

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

    /**
     * Creates an Endpoint instance from a callable or a string representation.
     *
     * @param mixed               $callable A callable, a string in the format 'Class::method' or 'object->method', or a stdClass object
     * @param array<string,mixed> $params   Parameters to pass to the method
     *
     * @return null|self Returns an Endpoint instance or null if the callable is invalid
     *
     * @throws \Exception If the callable is not valid or if the method is not static
     */
    public static function create(mixed $callable, array $params = []): ?self
    {
        if ($callable instanceof \stdClass) { // Probably a payload from a packet
            if (property_exists($callable->target, 'code')) {
                $callable->target = new Closure($callable->target);
            }

            return new self(
                $callable->target ?? '',
                $callable->method ?? '',
                $callable->params ?? []
            );
        }
        if ($callable instanceof \Closure) {
            return new self(
                new Closure($callable),
                '__invoke',
                $params,
            );
        }
        if (is_string($callable)) {
            if (false !== strpos($callable, '::')) {
                $callable = explode('::', $callable, 2);
            } elseif (false !== strpos($callable, '->')) {
                $callable = explode('->', $callable, 2);
            }
        }
        if (!is_array($callable)) {
            return null;
        }
        if (is_object($callable[0])) {
            $reflectionClass = new \ReflectionClass($callable[0]);
            if (!$reflectionClass->isSubclassOf('\Hazaar\Warlock\Process')) {
                $reflectionMethod = new \ReflectionMethod($callable[0], $callable[1]);
                $classname = $reflectionClass->getName();
                if (!$reflectionMethod->isStatic()) {
                    throw new \InvalidArgumentException('Method '.$callable[1].' of class '.$classname.' must be static');
                }
                $callable[0] = $classname;
            }
        } elseif (2 !== count($callable)) {
            throw new \InvalidArgumentException('Invalid callable definition!');
        }

        return new self(
            $callable[0],
            $callable[1],
            $callable[2] ?? []
        );
    }

    public function getName(): string
    {
        if (is_object($this->target)) {
            return get_class($this->target).'::'.$this->method;
        }

        return $this->target.'::'.$this->method;
    }

    public function getTarget(): mixed
    {
        return $this->target;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Retrieves the parameters associated with the endpoint.
     *
     * @return array<string,mixed> the parameters of the endpoint
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Sets the parameters for the Endpoint.
     *
     * @param array<string,mixed> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function run(Protocol $protocol): mixed
    {
        if (is_object($this->target)) {
            if ($this->target instanceof Closure) {
                $this->target->registerClass('Hazaar\Warlock\Channel');
            }

            return call_user_func_array([$this->target, $this->method], $this->params);
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

            return $methodReflection->invoke($container, ...$this->params);
        }
        if (!$methodReflection->isStatic()) {
            throw new \RuntimeException(sprintf(
                'Method %s::%s is not static.',
                $this->target,
                $this->method
            ));
        }

        return $methodReflection->invokeArgs(null, $this->params);
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

    /**
     * Converts the Endpoint to a JSON representation.
     *
     * @return array{target:string,method:string,params:array<string,mixed>} The JSON representation of the Endpoint
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
