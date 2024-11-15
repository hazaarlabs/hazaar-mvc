<?php

namespace Hazaar\Parser\PHP\Interfaces;

use Hazaar\Parser\PHP\Token;

interface TokenParser
{
    /**
     * @param array<string|Token>       $tokens the tokens to parse
     * @param null|array<string>|string $ns     the namespace of the token
     */
    public function parse(array &$tokens, null|array|string $ns = null): bool;
}
