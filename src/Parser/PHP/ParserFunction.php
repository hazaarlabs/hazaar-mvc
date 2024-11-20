<?php

namespace Hazaar\Parser\PHP;

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
     * The name of the function.
     */
    public string $name;

    /**
     * The return type of the function.
     */
    public string $returns = 'mixed';

    /**
     * @var array<ParserParameter>
     */
    public array $params = [];

    /**
     * The comment block for the function.
     *
     * @var null|array<mixed>
     */
    public ?array $comment = null;
    public int $line;

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
        if ($comment = $this->checkDocComment($tokens, $count > 1)) {
            $this->comment = $comment;
        }
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
            if ((is_string($token) && '{' === $token)
                || ($token instanceof Token && T_CURLY_OPEN == $token->type)) {
                break;
            }
            if (':' === $token) {
                $token = next($tokens);
                if ($token instanceof Token && T_STRING == $token->type) {
                    $this->returns = $token->value;
                }
            } elseif (0 === $depth && ')' !== $token) {
                $this->params[] = new ParserParameter($tokens);
            }
        }
        if ('variaticFunction' === $this->name) {
            echo '';
        }
        /*
         * If the function has no parameters, but params are defined in the docblock, we now
         * assume that this is an old-style variadic function (ie: uses func_get_args())
         * so we take the docblock parameters as is.
         */
        if ($this->comment
            && array_key_exists('tags', $this->comment)
            && array_key_exists('param', $this->comment['tags'])) {
            if (0 === count($this->params)) {
                foreach ($this->comment['tags']['param'] as $param) {
                    $functionParam = new ParserParameter();
                    $functionParam->name = ltrim($param['var'] ?? '', '$');
                    $functionParam->type = $param['type'] ?? 'mixed';
                    $functionParam->comment = $param['desc'] ?? '';
                    $this->params[] = $functionParam;
                }
            } else {
                foreach ($this->comment['tags']['param'] as $param) {
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
