<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\PHP\Traits\DocBlockParser;

class ParserClass extends TokenParser
{
    use DocBlockParser;

    public bool $abstract = false;
    public ?string $extends = null;

    /**
     * @var array<string>
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

    protected int $parserObjectType = T_CLASS;

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if ($this->parserObjectType !== $token->type) {
            return false;
        }
        $this->line = $token->line;
        $token = prev($tokens);
        if ($token instanceof Token && T_ABSTRACT == $token->type) {
            $this->abstract = true;
        }
        $token = next($tokens);
        $this->docBlock = $this->checkDocComment($tokens);
        prev($tokens);
        while ($token = next($tokens)) {
            if (!$token instanceof Token) {
                if ('{' == $token) {
                    break;
                }
            } else {
                switch ($token->type) {
                    case $this->parserObjectType:
                        $token = next($tokens);
                        $this->name = $token->value;

                        break;

                    case T_EXTENDS:
                        $extends = '';
                        while ($token = next($tokens)) {
                            if (!$token instanceof Token || !in_array($token->type, [
                                T_NAME_FULLY_QUALIFIED,
                                T_NS_SEPARATOR,
                                T_STRING,
                            ])) {
                                break;
                            }
                            $extends .= $token->value;
                        }
                        prev($tokens);
                        $this->extends = $this->namespace ? $this->namespace->apply($extends) : $extends;

                        break;

                    case T_IMPLEMENTS:
                        $implements = '';
                        while ($token = next($tokens)) {
                            if (',' == $token) {
                                $this->implements[] = $implements;
                                $implements = '';

                                continue;
                            }
                            if (!$token instanceof Token || match ($token->type) {
                                T_NAME_FULLY_QUALIFIED,
                                T_NS_SEPARATOR,
                                T_STRING => false,
                                default => true,
                            }) {
                                break;
                            }
                            $implements .= $token->value;
                        }
                        prev($tokens);
                        $this->implements[] = $implements;
                        if ($this->namespace) {
                            foreach ($this->implements as &$implement) {
                                $implement = $this->namespace->apply($implement);
                            }
                        }

                        break;
                }
            }
        }

        // Parse the class properties and methods
        while ($token = next($tokens)) {
            if (!$token instanceof Token) {
                if ('}' == $token) {
                    break;
                }
            } else {
                switch ($token->type) {
                    case T_VARIABLE:
                        $prop = new ParserProperty($tokens);
                        $this->properties[] = $prop;

                        break;

                    case T_CONST:
                        $const = new ParserConstant($tokens);
                        $this->constants[] = $const;

                        break;

                    case T_FUNCTION:
                        $func = new ParserFunction($tokens);
                        $this->methods[] = $func;

                        break;
                }
            }
        }

        return true;
    }
}
