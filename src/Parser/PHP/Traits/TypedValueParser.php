<?php

namespace Hazaar\Parser\PHP\Traits;

use Hazaar\Parser\PHP\Token;

trait TypedValueParser
{
    /**
     * @param array<string|Token> $tokens
     */
    protected function getTypedValue(array &$tokens, bool $prev_after = true): mixed
    {
        $token = current($tokens);
        if (!$token instanceof Token) {
            if ('[' !== $token) {
                return null;
            }
            $openBackets = 1;
            $value = '';
            while ($token = next($tokens)) {
                if (is_string($token)) {
                    if ('[' === $token) {
                        ++$openBackets;
                    } elseif (']' === $token) {
                        --$openBackets;
                    }
                    if (0 === $openBackets) {
                        break;
                    }
                    $value .= $token;
                } elseif ($token instanceof Token) {
                    $value .= $token->value;
                }
            }

            return '['.$value.']';
        }
        $value = null;
        if (T_CONSTANT_ENCAPSED_STRING == $token->type) {
            $value = trim($token->value, "'");
        } elseif (T_LNUMBER == $token->type) {
            $value = (int) $token->value;
        } elseif (T_DNUMBER == $token->type) {
            $value = (float) $token->value;
        } elseif (T_ARRAY == $token->type) {
            $value = [];
            while ($token = next($tokens)) {
                if ($token instanceof Token) {
                    if (T_CONSTANT_ENCAPSED_STRING == $token->type || T_DNUMBER == $token->type || T_LNUMBER == $token->type) {
                        if (($key = $this->getTypedValue($tokens, false)) === '') {
                            $key = '{empty}';
                        }
                        $token = next($tokens);
                        if ($token instanceof Token && T_DOUBLE_ARROW == $token->type) {
                            $token = next($tokens);
                            $value[$key] = $this->getTypedValue($tokens, false);
                        } else {
                            prev($tokens);
                            $value[] = $this->getTypedValue($tokens);
                        }
                    } elseif (T_ARRAY == $token->type) {
                        $value[] = $this->getTypedValue($tokens);
                    } else {
                        break;
                    }
                } elseif (',' === $token) {
                    continue;
                } else {
                    break;
                }
            }
            prev($tokens);
        } elseif (T_STRING == $token->type) {
            $value = match (strtolower($token->value)) {
                'true' => true,
                'false' => false,
                'null' => null,
                'array' => [],
                default => $token->value,
            };
        } elseif ($prev_after) {
            prev($tokens);
        }

        return $value;
    }
}
