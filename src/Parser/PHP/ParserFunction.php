<?php

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\PHP\Interfaces\TokenParser;
use Hazaar\Parser\PHP\Traits\DocBlockParser;

class ParserFunction implements TokenParser
{
    use DocBlockParser;

    /**
     * Indicates if the function is static.
     */
    public bool $static = false;

    /**
     * The namespace of the function.
     *
     * @var null|array<string>|string
     */
    public null|array|string $namespace = null;

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
    public ?string $comment = null;
    public int $line;

    /**
     * PHPFunction constructor.
     *
     * @param array<string|Token> $tokens
     */
    public function __construct(array &$tokens, ?string $namespace = null)
    {
        if (!$this->parse($tokens, $namespace)) {
            throw new \Exception('Failed to parse function');
        }
    }

    public function parse(array &$tokens, null|array|string $ns = null): bool
    {
        $token = current($tokens);
        if (T_FUNCTION !== $token->type) {
            return false;
        }
        $this->line = $token->line;
        if (is_array($ns)) {
            $this->namespace = $ns;
        }
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
            // $this->comment = $comment;
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
                return true;
            }
            if (':' === $token) {
                $token = next($tokens);
                if ($token instanceof Token && T_STRING == $token->type) {
                    $this->returns = $token->value;
                }
            } elseif (0 === $depth) {
                $this->params[] = new ParserParameter($tokens);
            }
        }
        /*
         * If the function has no parameters, but params are defined in the docblock, we now
         * assume that this is an old variadic function (ie: uses func_get_args() or something)
         * so we take the docblock parameters as is
         */
        // if (!array_key_exists('params', $func)
        //     && array_key_exists('comment', $func)
        //     && array_key_exists('tags', $func['comment'])
        //     && array_key_exists('param', $func['comment']['tags'])) {
        //     foreach ($func['comment']['tags']['param'] as $param) {
        //         $func['params'][] = array_merge($param, ['name' => $param['var']]);
        //     }
        // }

        return false;
    }
}
