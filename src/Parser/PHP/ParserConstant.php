<?php

namespace Hazaar\Parser\PHP;

class ParserConstant extends TokenParser
{
    use Traits\DocBlockParser;
    use Traits\TypedValueParser;

    public string $name;
    public int $line;

    /**
     * @var array<string>
     */
    public ?array $comment = null;
    public mixed $value;

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_CONST !== $token->type) {
            return false;
        }
        $token = next($tokens);
        $this->name = $token->value;
        $this->line = $token->line;
        if ($comment = $this->checkDocComment($tokens, true)) {
            $this->comment = $comment;
        }
        $token = next($tokens);
        $this->value = $this->getTypedValue($tokens, false);

        return true;
    }
}
