<?php

namespace Hazaar\Parser\PHP;

class ParserProperty extends TokenParser
{
    use Traits\DocBlockParser;
    use Traits\TypedValueParser;

    public int $line;
    public string $name;
    public string $type;
    public mixed $value;
    public bool $static = false;

    /**
     * @var array<string>
     */
    public ?array $comment = null;

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
                $this->type = $token->value;
            } elseif (T_STATIC == $token->type) {
                $this->static = true;
            } else {
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
        if (T_VARIABLE == $token->type) {
            $this->name = $token->value;
        }
        $token = next($tokens);
        if ($token instanceof Token) {
            $this->value = $this->getTypedValue($tokens);
        }

        return true;
    }
}
