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
                    $this->functions[] = new ParserFunction($tokens, $currentNamespace);

                    break;

                case T_NAMESPACE:
                    $this->namespaces[] = $currentNamespace = new ParserNamespace($tokens);

                    break;

                case T_INTERFACE:
                    $this->interfaces[] = new ParserInterface($tokens, $currentNamespace);

                    break;

                case T_CLASS:
                    $this->classes[] = new ParserClass($tokens, $currentNamespace);

                    break;
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
}
