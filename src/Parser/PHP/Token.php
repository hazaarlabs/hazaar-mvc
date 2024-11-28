<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

class Token
{
    public int $type;
    public mixed $value;
    public int $line;

    /**
     * Token constructor.
     *
     * @param array<mixed> $tokenData
     */
    public function __construct(array $tokenData)
    {
        $this->type = $tokenData[0];
        $this->value = $tokenData[1];
        $this->line = $tokenData[2];
    }

    public function getName(): string
    {
        return token_name($this->type);
    }
}
