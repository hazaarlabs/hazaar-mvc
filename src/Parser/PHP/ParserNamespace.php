<?php

namespace Hazaar\Parser\PHP;

class ParserNamespace extends TokenParser
{
    use Traits\DocBlockParser;

    public ?string $name = null;
    public string $comment = '';

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_NAMESPACE == $token->type) {
            $namespace = [
                'name' => [],
                'line' => $token->line,
            ];
            if ($comment = $this->checkDocComment($tokens)) {
                $this->comment = $comment['brief'] ?? '';
            }
            while ($token = next($tokens)) {
                if ($token instanceof Token) {
                    if (T_NS_SEPARATOR == $token->type) {
                        continue;
                    }
                    $namespace['name'][] = $token->value;
                } elseif (';' == $token) {
                    $this->name = implode('\\', $namespace['name']);

                    return true;
                }
            }
        }

        return false;
    }
}
