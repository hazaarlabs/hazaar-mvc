<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Closure.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

/**
 * The Hazaar Closure Class.
 *
 * This class is a wrapper around PHP's Closure class that allows
 * for the serialization of closures.  This is useful for storing
 * closures in a database or transmitting them over a network.
 */
class Closure implements \JsonSerializable
{
    protected \Closure|\stdClass $closure;
    protected \ReflectionFunction $reflection;
    private string $code;

    public function __construct(\Closure|\stdClass $function = null)
    {
        if ($function instanceof \Closure) {
            $this->closure = $function;
            $this->reflection = new \ReflectionFunction($function);
            $this->code = $this->_fetchCode();
        } elseif ($function instanceof \stdClass && isset($function->code)) {
            $this->code = $function->code;
            eval('$_function = '.rtrim($function->code, ' ;').';');
            $this->closure = $function;
            // @phpstan-ignore-next-line
            $this->reflection = new \ReflectionFunction($_function);
        }
    }

    public function __invoke(): mixed
    {
        $args = func_get_args();

        return $this->reflection->invokeArgs($args);
    }

    public function __toString(): string
    {
        return $this->getCode();
    }

    /**
     * @return array<string>
     */
    public function __sleep(): array
    {
        return ['code'];
    }

    public function __wakeup(): void
    {
        eval('$_function = '.$this->code.';');
        // @phpstan-ignore-next-line
        if (isset($_function) && $_function instanceof \Closure) {
            $this->closure = $_function;
            $this->reflection = new \ReflectionFunction($_function);
        } else {
            throw new Exception('Bad code: '.$this->code);
        }
    }

    public function getClosure(): \Closure
    {
        return $this->closure;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function loadCodeFromString(string $string): void
    {
        $this->code = $string;
        $this->__wakeup();
    }

    /**
     * @return array<\ReflectionParameter>
     */
    public function getParameters(): array
    {
        return $this->reflection->getParameters();
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
        ];
    }

    protected function _fetchCode(): string
    {
        $file = new \SplFileObject($this->reflection->getFileName());
        $file->seek($this->reflection->getStartLine() - 1);
        $code = '';
        while ($file->key() < $this->reflection->getEndLine()) {
            $code .= $file->current();
            $file->next();
        }
        // Only keep the code defining that closure
        $begin = strpos($code, 'function');
        $end = strrpos($code, '}');

        return substr($code, $begin, $end - $begin + 1);
    }
}
