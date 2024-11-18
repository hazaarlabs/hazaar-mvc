<?php

namespace Hazaar\Parser;

use Hazaar\Parser\PHP\ParserClass;
use Hazaar\Parser\PHP\ParserFunction;
use Hazaar\Parser\PHP\ParserInterface;
use Hazaar\Parser\PHP\ParserNamespace;

class PHP
{
    private string $source;
    private int $size = 0;
    private string $comment;

    /**
     * @var array<PHP\ParserFunction>
     */
    private array $functions = [];

    /**
     * @var array<PHP\ParserNamespace>
     */
    private array $namespaces = [];

    /**
     * @var array<PHP\ParserInterface>
     */
    private array $interfaces = [];

    /**
     * @var array<PHP\ParserClass>
     */
    private array $classes = [];

    private ?DocBlock $docBlockParser = null;

    public function __construct(bool $parseDocblocks = false)
    {
        if (true === $parseDocblocks) {
            $this->docBlockParser = new DocBlock();
        }
    }

    public function parse(string $filename): bool
    {
        if (!file_exists($filename)) {
            return false;
        }
        $tokens = $this->fixTokenArray(token_get_all(file_get_contents($filename)));
        $this->source = realpath($filename);
        $this->size = filesize($filename);
        $currentNamespace = null;
        while ($token = next($tokens)) {
            if (!$token instanceof PHP\Token) {
                continue;
            }

            switch ($token->type) {
                case T_FUNCTION:
                    $this->functions[] = new ParserFunction($tokens, ake($currentNamespace, 'name'));

                    break;

                case T_NAMESPACE:
                    $this->namespaces[] = $currentNamespace = new ParserNamespace($tokens);

                    break;
                    // case T_INTERFACE:
                    //     $this->interfaces[] = $this->parseClass($array, ake($currentNamespace, 'name'));

                    //     break;

                    // case T_CLASS:
                    //     $this->classes[] = $this->parseClass($array, ake($currentNamespace, 'name'));

                    //     break;

                    // case T_DOC_COMMENT:
                    //     if ($this->docBlockParser instanceof DocBlock) {
                    //         $this->docBlockParser->setComment($token->value);
                    //         if ($this->docBlockParser->hasTag('file')) {
                    //             $this->comment = $this->docBlockParser->toArray();
                    //         }
                    //     } else {
                    //         $this->comment = $token->value;
                    //     }

                    //     break;
            }
        }

        return true;
    }

    /**
     * @return array{source:string,size:int,comment:string,functions:array<ParserFunction>,namespaces:array<ParserNamespace>,interfaces:array<ParserInterface>,classes:array<ParserClass>}
     */
    public function getInfo(): array
    {
        return [
            'source' => $this->source,
            'size' => $this->size,
            // 'comment' => $this->comment,
            'namespaces' => $this->namespaces,
            'functions' => $this->functions,
            'interfaces' => $this->interfaces,
            'classes' => $this->classes,
        ];
    }

    /**
     * @param array<mixed> $tokens
     *
     * @return array<PHP\Token|string>
     */
    private function fixTokenArray(array $tokens): array
    {
        $fixedTokens = [];
        foreach ($tokens as $token) {
            if (is_array($token) && T_WHITESPACE != $token[0]) {
                $fixedTokens[] = new PHP\Token($token);
            } elseif (is_string($token)) {
                $fixedTokens[] = $token;
            }
        }

        return $fixedTokens;
    }

    private function parseConstant(&$ar)
    {
        $token = current($ar);
        if (T_CONST == $token->type) {
            $token = next($ar);
            $const = [
                'name' => $token->value,
                'line' => $token->line,
            ];
            if ($comment = $this->checkDocComment($ar, true)) {
                $const['comment'] = $comment;
            }
            $token = next($ar);
            $const['value'] = $this->getTypedValue($ar, false);

            return $const;
        }

        return null;
    }

    private function parseProperty(&$ar)
    {
        $token = current($ar);
        if (T_VARIABLE == $token->type) {
            $prop = [
                'line' => $token->line,
                'static' => false,
            ];
            $count = 0;
            while ($token = prev($ar)) {
                if (!is_array($token)) {
                    break;
                }
                if (T_PRIVATE == $token->type || T_PUBLIC == $token->type || T_PROTECTED == $token->type) {
                    $prop['type'] = $token->value;
                } elseif (T_STATIC == $token->type) {
                    $prop['static'] = true;
                } else {
                    break;
                }
                ++$count;
            }
            for ($i = 0; $i < $count; ++$i) {
                next($ar);
            }
            if ($comment = $this->checkDocComment($ar, $count > 1)) {
                $prop['comment'] = $comment;
            }
            $token = next($ar);
            if (T_VARIABLE == $token->type) {
                $prop['name'] = $token->value;
            }
            $token = next($ar);
            if (is_array($token)) {
                $prop['value'] = $this->getTypedValue($ar);
            }

            return $prop;
        }

        return null;
    }

    private function parseClass(&$ar, $ns = null)
    {
        $token = current($ar);
        if (T_CLASS == $token->type || T_INTERFACE == $token->type) {
            $class_info = [
                'line' => $token->line,
                'abstract' => false,
            ];
            $token = prev($ar);
            if (is_array($token) && T_ABSTRACT == $token->type) {
                $class_info['abstract'] = true;
            }
            $token = next($ar);
            if ($comment = $this->checkDocComment($ar, $class_info['abstract'])) {
                $class_info['comment'] = $comment;
            }
            if (is_array($ns)) {
                $class_info['namespace'] = $ns;
            }
            prev($ar);
            while ($token = next($ar)) {
                if (!is_array($token)) {
                    if ('}' == $token) {
                        break;
                    }
                } else {
                    switch ($token->type) {
                        case T_INTERFACE:
                        case T_CLASS:
                            $token = next($ar);
                            $class_info['name'] = $token->value;

                            break;

                        case T_EXTENDS:
                            $extends = '';
                            while ($token = next($ar)) {
                                if (!is_array($token) || !in_array($token->type, [
                                    T_NS_SEPARATOR,
                                    T_STRING,
                                ])) {
                                    break;
                                }
                                $extends .= $token->value;
                            }
                            prev($ar);
                            $class_info['extends'] = $extends;

                            break;

                        case T_IMPLEMENTS:
                            $implements = '';
                            while ($token = next($ar)) {
                                if (',' == $token) {
                                    $class_info['implements'][] = $implements;
                                    $implements = '';

                                    continue;
                                }
                                if (!is_array($token) || !in_array($token->type, [
                                    T_NS_SEPARATOR,
                                    T_STRING,
                                ])) {
                                    break;
                                }
                                $implements .= $token->value;
                            }
                            prev($ar);
                            $class_info['implements'][] = $implements;

                            break;

                        case T_VARIABLE:
                            $prop = $this->parseProperty($ar);
                            $class_info['properties'][] = $prop;

                            break;

                        case T_FUNCTION:
                            $func = $this->parseFunction($ar);
                            $class_info['methods'][] = $func;

                            break;

                        case T_CONST:
                            $const = $this->parseConstant($ar);
                            $class_info['constants'][] = $const;

                            break;
                    }
                }
            }

            return $class_info;
        }

        return null;
    }
}
