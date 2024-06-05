<?php

declare(strict_types=1);

namespace Hazaar\Exception;

abstract class CallerException extends \Exception
{
    public function __construct(string $message, int $stepBack = 0, int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $backtrace = debug_backtrace();
        $caller = $backtrace[$stepBack];
        $this->file = $caller['file'];
        $this->line = $caller['line'];
    }
}
