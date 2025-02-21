<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

class ParserParameter extends TokenParser
{
    use Traits\TypedValueParser;

    public ?string $type = null;
    public bool $isNullable = false;
    public mixed $default = null;
    public bool $byRef = false;
    public bool $variadic = false;
    public string $comment = '';

    public function __construct(?array &$tokens = null, bool $isNullable = false)
    {
        $this->isNullable = $isNullable;
        parent::__construct($tokens);
    }

    // public function __toString()
    // {
    //     return (true == $this->isNullable ? '?' : '')
    //         .$this->type.' $'.$this->name
    //         .(null !== $this->default ? ' = '.$this->default : '');
    // }

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        do {
            if ($token instanceof Token) {
                switch ($token->type) {
                    case T_ELLIPSIS:
                        $this->variadic = true;

                        break;

                    case T_VARIABLE:
                        $this->name = $name = ltrim($token->value, '$');

                        break;

                    case T_ARRAY:
                    case T_STRING:
                    case T_CALLABLE:
                    case T_NAME_RELATIVE:
                    case T_NAME_QUALIFIED:
                    case T_NAME_FULLY_QUALIFIED:
                        $this->type = $token->value;

                        break;

                    case T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG:
                        $this->byRef = true;

                        break;

                    default:
                        break 2;
                }
            } elseif (match ($token) {
                ';', ',', ')', '{' => true,
                default => false
            }) {
                if (match ($token) {
                    '{', ';' => true,
                    default => false
                }) {
                    prev($tokens);
                }

                return true;
            } elseif ('=' === $token) {
                $token = next($tokens);
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
        } while ($token = next($tokens));

        return false;
    }
}
