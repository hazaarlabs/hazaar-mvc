<?php

namespace Hazaar\Parser\PHP;

class TokenParser
{
    protected ?ParserNamespace $namespace;

    /**
     * @param array<Token> $tokens
     */
    public function __construct(?array &$tokens = null, ?ParserNamespace $namespace = null)
    {
        $this->namespace = $namespace;
        if (null === $tokens) {
            return;
        }
        if (!$this->parse($tokens)) {
            $parserType = strtolower(substr(get_class($this), 7));

            throw new \Exception('Failed to parse PHP '.$parserType);
        }
    }

    /**
     * @param array<string|Token> $tokens the tokens to parse
     */
    protected function parse(array &$tokens): bool
    {
        return false;
    }
}
