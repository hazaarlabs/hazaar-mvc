<?php

namespace Hazaar\Warlock\Agent\Struct;

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

    public function run(): void 
    {
        $callable = [
            $this->target,
            $this->method,
        ];
        call_user_func_array($callable, (array) $this->params);
    }
}
