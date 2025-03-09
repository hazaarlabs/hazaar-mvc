<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Str;

class Modifier
{
    /**
     * @var array<string, array{object, array<int, \ReflectionParameter>}>
     */
    private array $loadedModifiers = [];

    /**
     * Executes a modifier function by its name with the provided value and arguments.
     *
     * @param string $name    the name of the modifier function to execute
     * @param mixed  $value   the value to be passed to the modifier function
     * @param mixed  ...$args Additional arguments to be passed to the modifier function.
     *
     * @return mixed the result of the modifier function execution
     *
     * @throws \Exception if the modifier class does not exist
     */
    public function execute(string $name, mixed $value, mixed ...$args): mixed
    {
        if (array_key_exists($name, $this->loadedModifiers)) {
            return $this->runWithArgs($name, $value, ...$args);
        }
        $className = 'Hazaar\Template\Smarty\Modifier\\'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if (Str::isReserved($name)) {
            $className .= 'Modifier';
        }
        if (!class_exists($className)) {
            throw new \Exception('Modifier '.$name.' does not exist!');
        }
        $argTypes = [];
        $reflectionMethod = new \ReflectionMethod($className, 'run');
        foreach ($reflectionMethod->getParameters() as $index => $parameter) {
            $argTypes[] = $parameter;
        }
        $this->loadedModifiers[$name] = [
            new $className(),
            $argTypes,
        ];

        return $this->runWithArgs($name, $value, ...$args);
    }

    /**
     * Executes a modifier with the provided arguments.
     *
     * Arguments are passed to the modifier in the order they are provided and
     * are cast to the type expected by the modifier.
     *
     * @param string $name    the name of the modifier to run
     * @param mixed  $value   the initial value to pass to the modifier
     * @param mixed  ...$args Additional arguments to pass to the modifier.
     *
     * @return mixed the result of the modifier execution
     *
     * @throws \Exception      if a required argument is missing
     * @throws \LogicException if a union or intersection type is encountered
     */
    public function runWithArgs(string $name, mixed $value, mixed ...$args): mixed
    {
        $args = array_merge([$value], $args);
        [$callable, $argTypes] = $this->loadedModifiers[$name];
        foreach ($argTypes as $index => $reflectionParameter) {
            if (!array_key_exists($index, $args)) {
                if (!$reflectionParameter->isOptional()) {
                    throw new \Exception('Missing argument '.$index.' for modifier '.$name.'!');
                }
                $args[$index] = $reflectionParameter->getDefaultValue();
            } else {
                $type = $reflectionParameter->getType();
                if (!$type instanceof \ReflectionNamedType) {
                    throw new \LogicException('Union or intersection types not supported here.');
                }
                $paramType = $type->getName();
                if ('mixed' !== $paramType) {
                    settype($args[$index], $paramType);
                }
            }
        }

        return $callable->run(...$args);
    }
}
