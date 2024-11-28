<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;

class ParserFile extends TokenParser
{
    private string $source;
    private int $size = 0;

    /**
     * @var array<ParserConstant>
     */
    private array $constants = [];

    /**
     * @var array<ParserFunction>
     */
    private array $functions = [];

    /**
     * @var array<ParserInterface>
     */
    private array $interfaces = [];

    /**
     * @var array<ParserClass>
     */
    private array $classes = [];

    public function __construct(string $filename)
    {
        $this->source = realpath($filename);
        $this->size = filesize($filename);
        $tokens = $this->fixTokenArray(token_get_all(file_get_contents($filename)));
        while ($token = next($tokens)) {
            if (!$token instanceof Token) {
                continue;
            }

            try {
                switch ($token->type) {
                    case T_CONST:
                        $this->constants[] = new ParserConstant($tokens, $this->namespace);

                        break;

                    case T_FUNCTION:
                        $this->functions[] = new ParserFunction($tokens, $this->namespace);

                        break;

                    case T_NAMESPACE:
                        $this->namespace = new ParserNamespace($tokens);

                        break;

                    case T_INTERFACE:
                        $this->interfaces[] = new ParserInterface($tokens, $this->namespace);

                        break;

                    case T_CLASS:
                        $this->classes[] = new ParserClass($tokens, $this->namespace);

                        break;

                    case T_DOC_COMMENT:
                        $docBlock = new DocBlock($token->value);
                        if ($docBlock->hasTag('file')) {
                            $this->docBlock = $docBlock;
                        }

                        break;
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage().' in '.$this->source);
            }
        }
    }

    /**
     * Returns the source file path.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Returns the size of the file in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Returns an array of constants defined in the file.
     *
     * @return array<ParserConstant>
     */
    public function getConstants(): array
    {
        return $this->constants;
    }

    /**
     * Returns an array of functions defined in the file.
     *
     * @return array<ParserFunction>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Returns an array of interfaces defined in the file.
     *
     * @return array<ParserInterface>
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * Returns an array of classes defined in the file.
     *
     * @return array<ParserClass>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getDocBlock(): ?DocBlock
    {
        return $this->docBlock;
    }

    /**
     * @param array<mixed> $tokens
     *
     * @return array<string|Token>
     */
    private function fixTokenArray(array $tokens): array
    {
        $fixedTokens = [];
        foreach ($tokens as $token) {
            if (is_array($token) && T_WHITESPACE != $token[0]) {
                $fixedTokens[] = new Token($token);
            } elseif (is_string($token)) {
                $fixedTokens[] = $token;
            }
        }

        return $fixedTokens;
    }
}
