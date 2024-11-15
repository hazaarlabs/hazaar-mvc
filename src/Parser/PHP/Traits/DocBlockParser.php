<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP\Traits;

use Hazaar\Parser\DocBlock;
use Hazaar\Parser\PHP\Token;

trait DocBlockParser
{
    /**
     * @param array<Token> $tokens the tokens to parse
     *
     * @return null|array<string,mixed>
     */
    private function checkDocComment(array &$tokens, bool $doubleJump = false): ?array
    {
        $docBlockComment = null;
        $docBlackParser = new DocBlock();
        if ($doubleJump) {
            prev($tokens);
        }
        // Peak at the previous token to see if it is a comment and if so return the comment.
        if ($token = prev($tokens)) {
            if ($token instanceof Token && T_DOC_COMMENT == $token->type) {
                $docBlackParser->setComment($token->value);
                if (!$docBlackParser->hasTag('file')) {
                    $docBlockComment = $docBlackParser->toArray();
                    unset($docBlockComment['comment']);
                } else {
                    $docBlockComment = $token->value;
                }
            }
            next($tokens);
        }
        if ($doubleJump) {
            next($tokens);
        }

        return $docBlockComment;
    }
}
