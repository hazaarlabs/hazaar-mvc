<?php

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\PHP\Interfaces\TokenParser;

class ParserNamespace implements TokenParser
{
    use Traits\DocBlockParser;

    public ?string $name = null;
    public string $comment = '';

    /**
     * @param array<Token> $tokens
     */
    public function __construct(array &$tokens)
    {
        if (!$this->parse($tokens)) {
            throw new \Exception('Failed to parse PHP namespace');
        }
    }

    public function parse(array &$tokens, null|array|string $ns = null): bool
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
