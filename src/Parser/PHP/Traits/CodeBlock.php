<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP\Traits;

use Hazaar\Parser\PHP\Token;

trait CodeBlock
{
    /**
     * Find a code block in the token stream.
     *
     * This function will return an array of tokens that represent the code block found.
     *
     * @param array<string|Token> $tokens the token stream to search
     */
    protected function seekCodeBlockEnd(array &$tokens, string $start = '{', string $end = '}'): bool
    {
        $openBrackets = 1;
        $inQuote = false;
        while ($token = next($tokens)) {
            if (!is_string($token)) {
                continue;
            }
            if ($inQuote) {
                if ('"' === $token || "'" === $token) {
                    $inQuote = false;
                }

                continue;
            }
            if ('"' === $token || "'" === $token) {
                $inQuote = true;

                continue;
            }
            if ($start === $token) {
                ++$openBrackets;
            } elseif ($end === $token) {
                --$openBrackets;
            }
            if (0 === $openBrackets) {
                return true;
            }
        }

        return false;
    }
}
