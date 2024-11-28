<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;
use Hazaar\Parser\PHP\Traits\CodeBlock;
use Hazaar\Parser\PHP\Traits\DocBlockParser;

class ParserFunction extends TokenParser
{
    use DocBlockParser;
    use CodeBlock;

    /**
     * Indicates if the function is static.
     */
    public bool $static = false;

    /**
     * The return access modifier of the function.
     */
    public ?string $access = 'public';

    public bool $byRef = false;

    /**
     * The return type of the function.
     */
    public ?ParserParameter $returns = null;

    /**
     * @var array<ParserParameter>
     */
    public array $params = [];

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
        if (T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG === $token->type) {
            $this->byRef = true;
            $token = next($tokens);
        }
        if (T_STRING == $token->type) {
            $this->name = $token->value;
        }
        $token = next($tokens);
        if ('(' != $token) {
            throw new \Exception('Expected "("');
        }
        while ($token = next($tokens)) {
            if ((is_string($token) && ('{' === $token || ';' === $token))
                || ($token instanceof Token && T_CURLY_OPEN == $token->type)) {
                break;
            }
            $isNullable = false;
            if (':' === $token) {
                $token = next($tokens);
                if ('?' === $token) {
                    $isNullable = true;
                    $token = next($tokens);
                }
                if ($token instanceof Token && T_STRING == $token->type) {
                    $this->returns = new ParserParameter($tokens, $isNullable);
                }
            } elseif (')' !== $token) {
                if ('?' === $token) {
                    $isNullable = true;
                    $token = next($tokens);
                }
                $this->params[] = new ParserParameter($tokens, $isNullable);
            }
        }
        // If the next token is a semicolon, then this is a function prototype and we can stop here.
        if (';' === $token) {
            return true;
        }
        // Find the end of the function by searching for it's closing bracket.
        if (!$this->seekCodeBlockEnd($tokens, '{', '}')) {
            return false;
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
                        if (!$functionParam->type) {
                            $functionParam->type = $param['type'] ?? 'mixed';
                        }
                    }
                }
            }
        }

        return true;
    }
}
