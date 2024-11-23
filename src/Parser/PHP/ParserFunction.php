<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;
use Hazaar\Parser\PHP\Traits\DocBlockParser;

class ParserFunction extends TokenParser
{
    use DocBlockParser;

    /**
     * Indicates if the function is static.
     */
    public bool $static = false;

    /**
     * The return access modifier of the function.
     */
    public ?string $access = 'public';

    /**
     * The return type of the function.
     */
    public ?ParserParameter $returns = null;

    /**
     * @var array<ParserParameter>
     */
    public array $params = [];

    public ?DocBlock $docBlock = null;

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_FUNCTION !== $token->type) {
            return false;
        }
        $this->line = $token->line;
        $count = 0;
        while ($token = prev($tokens)) {
            if (!$token instanceof Token) {
                break;
            }
            if (T_PRIVATE == $token->type || T_PUBLIC == $token->type || T_PROTECTED == $token->type) {
                $this->access = $token->value;
            } elseif (T_STATIC == $token->type) {
                $this->static = true;
            } else {
                if (0 == $count) {
                    ++$count;
                }

                break;
            }
            ++$count;
        }
        for ($i = 0; $i < $count; ++$i) {
            next($tokens);
        }
        $this->docBlock = $this->checkDocComment($tokens);
        $token = next($tokens);
        if (T_FUNCTION == $token->type) {
            $token = next($tokens);
        }
        if (T_STRING == $token->type) {
            $this->name = $token->value;
        }
        $token = next($tokens);
        if ('(' != $token) {
            throw new \Exception('Expected "("');
        }
        $depth = 0;
        while ($token = next($tokens)) {
            if ((is_string($token) && ('{' === $token || ';' === $token))
                || ($token instanceof Token && T_CURLY_OPEN == $token->type)) {
                break;
            }
            $nullable = false;
            if (':' === $token) {
                $token = next($tokens);
                if ('?' === $token) {
                    $nullable = true;
                    $token = next($tokens);
                }
                if ($token instanceof Token && T_STRING == $token->type) {
                    $this->returns = new ParserParameter($tokens, $nullable);
                }
            } elseif (0 === $depth && ')' !== $token) {
                if ('?' === $token) {
                    $nullable = true;
                    $token = next($tokens);
                }
                $this->params[] = new ParserParameter($tokens, $nullable);
            }
        }
        // If the next token is a semicolon, then this is a function prototype and we can stop here.
        if (';' === $token) {
            return true;
        }

        /**
         * Find the end of the function by searching for it's closing bracket.
         */
        $openBrackets = 1;
        while ($token = next($tokens)) {
            if ($token instanceof Token
                && match ($token->type) {
                    T_STATIC, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_CLASS, T_INTERFACE => true,
                    default => false,
                }) {
                prev($tokens);

                break;
            }
            if (!is_string($token)) {
                continue;
            }
            if ('{' === $token) {
                ++$openBrackets;
            } elseif ('}' === $token) {
                --$openBrackets;
            }
            if (0 === $openBrackets) {
                break;
            }
        }
        /*
         * If the function has no parameters, but params are defined in the docblock, we now
         * assume that this is an old-style variadic function (ie: uses func_get_args())
         * so we take the docblock parameters as is.
         */
        if ($this->docBlock instanceof DocBlock
            && $this->docBlock->hasTag('param')) {
            if (0 === count($this->params)) {
                foreach ($this->docBlock->tag('param') as $param) {
                    $functionParam = new ParserParameter();
                    $functionParam->name = ltrim($param['var'] ?? '', '$');
                    $functionParam->type = $param['type'] ?? 'mixed';
                    $functionParam->comment = $param['desc'] ?? '';
                    $this->params[] = $functionParam;
                }
            } else {
                foreach ($this->docBlock->tag('param') as $param) {
                    $name = ltrim($param['var'], '$');
                    $functionParam = current(array_filter($this->params, function ($item) use ($name) {
                        return $item->name === $name;
                    }));
                    if ($functionParam) {
                        $functionParam->comment = $param['desc'] ?? '';
                    }
                }
            }
        }

        return true;
    }
}
