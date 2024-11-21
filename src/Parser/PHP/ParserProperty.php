<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;

class ParserProperty extends TokenParser
{
    use Traits\DocBlockParser;
    use Traits\TypedValueParser;

    public int $line;
    public string $type;
    public string $access = 'public';
    public mixed $value;
    public bool $static = false;

    public ?DocBlock $docBlock = null;

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_VARIABLE !== $token->type) {
            return false;
        }
        $this->line = $token->line;
        $this->static = false;
        $count = 0;
        while ($token = prev($tokens)) {
            if (!$token instanceof Token) {
                break;
            }
            if (T_PRIVATE == $token->type || T_PUBLIC == $token->type || T_PROTECTED == $token->type) {
                $this->access = $token->value;
            } elseif (T_STATIC == $token->type) {
                $this->static = true;
            } elseif (T_STRING == $token->type) {
                $this->type = $token->value;
            } else {
                break;
            }
            ++$count;
        }
        next($tokens);
        $this->docBlock = $this->checkDocComment($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = next($tokens);
        }
        if (T_VARIABLE == $token->type) {
            $this->name = ltrim($token->value, '$');
        }
        $token = next($tokens);
        if (is_string($token) && '=' === $token) {
            next($tokens);
            $this->value = $this->getTypedValue($tokens);
        }

        return true;
    }
}
