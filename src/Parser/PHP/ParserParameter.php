<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\PHP\Interfaces\TokenParser;

class ParserParameter implements TokenParser
{
    public string $name;
    public ?string $type = null;
    public mixed $default = null;
    public bool $byRef = false;
    public bool $variadic = false;
    public string $comment = '';

    /**
     * @param array<Token> $tokens
     */
    public function __construct(?array &$tokens = null)
    {
        if (null === $tokens) {
            return;
        }
        if (!$this->parse($tokens)) {
            throw new \Exception('Failed to parse PHP parameter');
        }
    }

    public function parse(array &$tokens, null|array|string $ns = null): bool
    {
        $token = current($tokens);
        do {
            if (!$token instanceof Token && (',' === $token || ')' === $token)) {
                return true;
            }

            if ($token instanceof Token) {
                switch ($token->type) {
                    case T_ELLIPSIS:
                        $this->variadic = true;

                        break;

                    case T_VARIABLE:
                        $this->name = $name = ltrim($token->value, '$');

                        break;

                    case T_STRING:
                        $this->type = $token->value;

                        break;

                    case T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG:
                        $this->byRef = true;

                        break;

                    default:
                        break 2;
                }
            } elseif ('=' === $token) {
                $token = next($tokens);
                if ($token instanceof Token) {
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
            }
        } while ($token = next($tokens));

        return false;
        //     $name = ltrim($param['name'], '$');
        //     // if (is_array($comment) && array_key_exists('tags', $comment)
        //     //     && array_key_exists('param', $comment['tags'])) {
        //     //     $param_doc = current(array_filter($comment['tags']['param'], function ($item) use ($name) {
        //     //         return ltrim($item['var'], '$') === $name;
        //     //     }));
        //     //     if ($param_doc) {
        //     //         $param = array_merge($param_doc, $param);
        //     //     }
        //     // }
        //     $this->params[] = $param;
        //     $type = null;
        // } else {
        //     $type .= $token->value;
        // }
    }

    /**
     * @param array<string|Token> $tokens
     */
    private function getTypedValue(array &$tokens, bool $prev_after = true): mixed
    {
        $token = current($tokens);
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
            $value = strtolower($token->value);

            switch ($value) {
                case 'true':
                    $value = true;

                    break;

                case 'false':
                    $value = false;

                    break;

                case 'null':
                    $value = null;

                    break;

                case 'array':
                    $value = [];

                    break;
            }
        } elseif ($prev_after) {
            prev($tokens);
        }

        return $value;
    }
}
