<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP\Traits;

use Hazaar\Parser\DocBlock;
use Hazaar\Parser\PHP\Token;

trait DocBlockParser
{
    /**
     * @param array<Token|string> $tokens the tokens to parse
     */
    protected function checkDocComment(array &$tokens, bool $doubleJump = false): ?DocBlock
    {
        $docBlock = null;
        if ($doubleJump) {
            prev($tokens);
        }
        // Peak at the previous token to see if it is a comment and if so return the comment.
        if ($token = prev($tokens)) {
            if ($token instanceof Token && T_DOC_COMMENT == $token->type) {
                $docBlock = new DocBlock($token->value);
            }
            next($tokens);
        }
        if ($doubleJump) {
            next($tokens);
        }

        return $docBlock;
    }
}
