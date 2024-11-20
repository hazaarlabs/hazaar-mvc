<?php

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\PHP\Traits\DocBlockParser;

class ParserClass extends TokenParser
{
    use DocBlockParser;

    public bool $abstract = false;
    public int $line;
    public string $name;
    public ?string $extends = null;

    /**
     * @var array<ParserInterface>
     */
    public array $implements = [];

    /**
     * @var array<ParserProperty>
     */
    public array $properties = [];

    /**
     * @var array<ParserFunction>
     */
    public array $methods = [];

    /**
     * @var array<ParserConstant>
     */
    public array $constants = [];

    /**
     * @var array<string>
     */
    public array $comment = [];

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (!(T_CLASS == $token->type || T_INTERFACE == $token->type)) {
            return false;
        }
        $this->line = $token->line;
        $token = prev($tokens);
        if ($token instanceof Token && T_ABSTRACT == $token->type) {
            $this->abstract = true;
        }
        $token = next($tokens);
        if ($comment = $this->checkDocComment($tokens, $this->abstract)) {
            $this->comment = $comment;
        }
        prev($tokens);
        while ($token = next($tokens)) {
            if (!$token instanceof Token) {
                if ('}' == $token) {
                    break;
                }
            } else {
                switch ($token->type) {
                    case T_INTERFACE:
                    case T_CLASS:
                        $token = next($tokens);
                        $this->name = $token->value;

                        break;

                    case T_EXTENDS:
                        $extends = '';
                        while ($token = next($tokens)) {
                            if (!$token instanceof Token || !in_array($token->type, [
                                T_NS_SEPARATOR,
                                T_STRING,
                            ])) {
                                break;
                            }
                            $extends .= $token->value;
                        }
                        prev($tokens);
                        $this->extends = $extends;

                        break;

                    case T_IMPLEMENTS:
                        $implements = '';
                        while ($token = next($tokens)) {
                            if (',' == $token) {
                                $this->implements[] = $implements;
                                $implements = '';

                                continue;
                            }
                            if (!$token instanceof Token || !in_array($token->type, [
                                T_NS_SEPARATOR,
                                T_STRING,
                            ])) {
                                break;
                            }
                            $implements .= $token->value;
                        }
                        prev($tokens);
                        $this->implements[] = $implements;

                        break;

                    case T_VARIABLE:
                        $prop = new ParserProperty($tokens);
                        $this->properties[] = $prop;

                        break;

                    case T_FUNCTION:
                        $func = new ParserFunction($tokens);
                        $this->methods[] = $func;

                        break;

                    case T_CONST:
                        $const = new ParserConstant($tokens);
                        $this->constants[] = $const;

                        break;
                }
            }
        }

        return true;
    }
}
