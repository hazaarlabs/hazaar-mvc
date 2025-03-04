<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

class Logger
{
    private static int $logLevel = W_INFO;
    private int $__logLevel;

    /**
     * @var array<int>
     */
    private array $__levels = [];
    private int $__strPad = 0;

    public function __construct(null|int|string $level = null)
    {
        $this->setLevel($level);
        $consts = get_defined_constants(true);
        // Load the warlock log levels into an array.
        foreach ($consts['user'] as $name => $value) {
            if ('W_' == substr($name, 0, 2)) {
                $len = strlen($this->__levels[$value] = substr($name, 2));
                if ($len > $this->__strPad) {
                    $this->__strPad = $len;
                }
            }
        }
    }

    public static function setDefaultLogLevel(int|string $level): void
    {
        if (is_string($level)) {
            $level = constant($level);
        }
        Logger::$logLevel = $level;
    }

    public static function getDefaultLogLevel(): int
    {
        return Logger::$logLevel;
    }

    /**
     * @param array<string>|\stdClass|string $message
     */
    public function write(int $level, array|\stdClass|string $message, ?string $task = null): void
    {
        if ($level <= $this->__logLevel) {
            echo date('Y-m-d H:i:s').' - ';
            $label = $this->__levels[$level] ?? 'NONE';
            if (is_array($message) || $message instanceof \stdClass) {
                $message = 'Received '.gettype($message)."\n".print_r($message, true);
            }
            echo str_pad($label, $this->__strPad, ' ', STR_PAD_LEFT).' - '.($task ? $task.' - ' : '').$message."\n";
        }
    }

    public function setLevel(null|int|string $level = null): void
    {
        if (null === $level) {
            $level = Logger::$logLevel;
        }
        if (is_string($level)) {
            $level = constant($level);
        }
        $this->__logLevel = $level;
    }

    public function getLevel(): int
    {
        return $this->__logLevel;
    }

    public function getLevelName(): string
    {
        return $this->__levels[$this->__logLevel] ?? 'NONE';
    }
}
