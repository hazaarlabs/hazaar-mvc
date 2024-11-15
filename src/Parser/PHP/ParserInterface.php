<?php

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\PHP\Interfaces\TokenParser;

class ParserInterface extends TokenParser
{
    /**
     * @param array<Token> $tokens
     */
    public function __construct(array &$tokens)
    {
        if (!$this->parse($tokens)) {
            throw new \Exception('Failed to parse PHP interface');
        }
    }

    public function parse(array &$tokens, null|array|string $ns = null): bool
    {
        return true;
    }
}
