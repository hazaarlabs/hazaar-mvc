<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

class ParserParameter extends TokenParser
{
    use Traits\TypedValueParser;

    public ?string $type = null;
    public mixed $default = null;
    public bool $byRef = false;
    public bool $variadic = false;
    public string $comment = '';

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        do {
            if (!$token instanceof Token && (',' === $token || ')' === $token)) {
                return true;
            }

            if ($token instanceof Token) {
                switch ($token->type) {
                    case T_ELLIPSIS:
                        $this->variadic = true;

                        break;

                    case T_VARIABLE:
                        $this->name = $name = ltrim($token->value, '$');

                        break;

                    case T_STRING:
                        $this->type = $token->value;

                        break;

                    case T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG:
                        $this->byRef = true;

                        break;

                    default:
                        break 2;
                }
            } elseif ('=' === $token) {
                $token = next($tokens);
                if ($token instanceof Token) {
                    $this->default = $this->getTypedValue($tokens);
                    if (!$this->type) {
                        $this->type = gettype($this->default);
                        if ('integer' === $this->type) {
                            $this->type = 'int';
                        } elseif ('double' === $this->type) {
                            $this->type = 'float';
                        }
                    }
                }
            }
        } while ($token = next($tokens));

        return false;
    }
}
