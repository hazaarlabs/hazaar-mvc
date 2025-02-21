<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;

class ParserProperty extends TokenParser
{
    use Traits\DocBlockParser;
    use Traits\TypedValueParser;
    use Traits\CodeBlock;

    public ?string $type = null;
    public bool $nullable = false;
    public string $access = 'public';
    public mixed $value = null;
    public bool $static = false;

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
                if($token === '?'){
                    $this->nullable = true;
                }
                break;
            }
            if (T_PRIVATE == $token->type || T_PUBLIC == $token->type || T_PROTECTED == $token->type) {
                $this->access = $token->value;
            } elseif (T_STATIC == $token->type) {
                $this->static = true;
            } elseif (T_STRING == $token->type || T_ARRAY == $token->type) {
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
        if (is_string($token) ){
            if('=' === $token) {
                next($tokens);
                $this->value = $this->getTypedValue($tokens);
            }elseif('{' === $token){
                $this->seekCodeBlockEnd($tokens, '{', '}');
            }
        }

        return true;
    }
}
