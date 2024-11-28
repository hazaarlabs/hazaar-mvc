<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;

class ParserConstant extends TokenParser
{
    use Traits\DocBlockParser;
    use Traits\TypedValueParser;

    public mixed $value = null;

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_CONST !== $token->type) {
            return false;
        }
        $token = next($tokens);
        $this->name = $token->value;
        $this->line = $token->line;
        $this->docBlock = $this->checkDocComment($tokens);
        $token = next($tokens);
        if (is_string($token) && '=' === $token) {
            next($tokens);
            $this->value = $this->getTypedValue($tokens, false);
        }

        return true;
    }
}
