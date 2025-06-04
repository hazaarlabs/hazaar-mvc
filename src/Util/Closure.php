<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Closure.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Util;

/**
 * The Hazaar Closure Class.
 *
 * This class is a wrapper around PHP's Closure class that allows
 * for the serialization of closures.  This is useful for storing
 * closures in a database or transmitting them over a network.
 *
 * It supports both standard closures and arrow functions (fn).
 *
 * The class can serialize the closure code to a string and
 * deserialize it back to a closure.
 *
 * It also provides methods to invoke the closure, get its
 * parameters, and fetch its code.
 */
class Closure implements \JsonSerializable
{
    protected \Closure|\stdClass $closure;
    protected \ReflectionFunction $reflection;
    private string $code;

    /**
     * Closure constructor.
     *
     * @param null|\Closure|\stdClass $function the closure or stdClass containing code to wrap
     */
    public function __construct(null|\Closure|\stdClass $function = null)
    {
        if ($function instanceof \Closure) {
            $this->closure = $function;
            $this->reflection = new \ReflectionFunction($function);
        // $this->code = $this->fetchCode();
        } elseif ($function instanceof \stdClass && isset($function->code)) {
            $this->code = $function->code;
            eval('$_function = '.rtrim($function->code, ' ;').';');
            $this->closure = $function;
            // @phpstan-ignore-next-line
            $this->reflection = new \ReflectionFunction($_function);
        }
    }

    /**
     * Invoke the wrapped closure with the given arguments.
     *
     * @return mixed the result of the closure execution
     */
    public function __invoke(): mixed
    {
        $args = func_get_args();

        return $this->reflection->invokeArgs($args);
    }

    /**
     * Get the closure code as a string.
     *
     * @return string the closure code
     */
    public function __toString(): string
    {
        return $this->getCode();
    }

    /**
     * Prepare the object for serialization.
     *
     * @return array<string> the list of properties to serialize
     */
    public function __sleep(): array
    {
        if (!isset($this->code)) {
            // If the code is not set, fetch it from the reflection
            $this->code = $this->fetchCode();
        }

        return ['code'];
    }

    /**
     * Restore the object after deserialization.
     *
     * @throws \Exception if the code cannot be converted back to a closure
     */
    public function __wakeup(): void
    {
        eval('$_function = '.$this->code.';');
        // @phpstan-ignore-next-line
        if (isset($_function) && $_function instanceof \Closure) {
            $this->closure = $_function;
            $this->reflection = new \ReflectionFunction($_function);
        } else {
            throw new \Exception('Bad code: '.$this->code);
        }
    }

    /**
     * Get the wrapped closure.
     *
     * @return \Closure the wrapped closure
     */
    public function getClosure(): \Closure
    {
        return $this->closure;
    }

    /**
     * Get the code for the closure.
     *
     * @return string the closure code
     */
    public function getCode(): string
    {
        return isset($this->code) ? $this->code : $this->code = $this->fetchCode();
    }

    /**
     * Load closure code from a string and re-initialize the closure.
     *
     * @param string $string the closure code as a string
     */
    public function loadCodeFromString(string $string): void
    {
        $this->code = $string;
        $this->__wakeup();
    }

    /**
     * Get the parameters of the closure.
     *
     * @return array<\ReflectionParameter> the closure parameters
     */
    public function getParameters(): array
    {
        return $this->reflection->getParameters();
    }

    /**
     * Serialize the closure to JSON.
     *
     * @return array<mixed> the serialized closure data
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
        ];
    }

    /**
     * Fetch the code for the closure from its source file.
     *
     * @return string the closure code
     *
     * @throws \Exception if the closure code cannot be extracted
     */
    protected function fetchCode(): string
    {
        $file = new \SplFileObject($this->reflection->getFileName());
        $file->seek($this->reflection->getStartLine() - 1);
        $code = '';
        while ($file->key() < $this->reflection->getEndLine()) {
            $code .= $file->current();
            $file->next();
        }
        // Support both 'function' and 'fn' (arrow function)
        $begin = strpos($code, 'function');
        if (false !== $begin) {
            // Standard closure
            $end = strrpos($code, '}');
            if (false === $end) {
                throw new \Exception('Invalid closure code: '.$code);
            }

            return substr($code, $begin, $end - $begin + 1);
        }
        $begin = strpos($code, 'fn');
        if (false === $begin) {
            throw new \Exception('Invalid closure code: '.$code);
        }
        $arrowPos = strpos($code, '=>', $begin);
        // Extract the arrow function body using regex
        $body = substr($code, $arrowPos + 2);
        // Check for balanced brackets
        $open = substr_count($body, '(');
        if (!preg_match('/^(.*?\)){'.$open.'}/', $body, $matches)) {
            throw new \Exception('Invalid arrow function code: '.$code);
        }

        return substr($code, $begin, ($arrowPos + 2) - $begin).$matches[0];
    }
}
