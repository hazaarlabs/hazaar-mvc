<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

class ParserConstant extends TokenParser
{
    use Traits\DocBlockParser;
    use Traits\TypedValueParser;

    public mixed $value = null;
    public ?string $access = null;

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_CONST !== $token->type) {
            return false;
        }
        $token = prev($tokens);
        if (is_object($token)
            && (T_PUBLIC === $token->type || T_PROTECTED === $token->type || T_PRIVATE === $token->type)) {
            $this->access = $token->value;
        }
        next($tokens);
        $this->docBlock = $this->checkDocComment($tokens);
        $token = next($tokens);
        $this->name = $token->value;
        $this->line = $token->line;
        $token = next($tokens);
        if (is_string($token) && '=' === $token) {
            next($tokens);
            $this->value = $this->getTypedValue($tokens, false);
        }

        return true;
    }
}
