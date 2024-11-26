<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;

class TokenParser
{
    public ?ParserNamespace $namespace = null;
    public ?string $name = null;
    public ?int $line = null;

    public ?DocBlock $docBlock = null;

    public ?string $fullName {
        get {
            return $this->namespace ? $this->namespace->apply($this->name) : $this->name;
        }
    }

    public ?string $brief {
        get {
            return $this->docBlock ? $this->docBlock->brief() : null;
        }
    }

    public ?string $detail {
        get {
            return $this->docBlock ? $this->docBlock->detail() : null;
        }
    }

    /**
     * @param array<Token|string> $tokens
     * @param-out array<Token|string> $tokens
     */
    public function __construct(?array &$tokens = null, ?ParserNamespace $namespace = null)
    {
        $this->namespace = $namespace;
        if (!$this->parse($tokens)) {
            $parserType = strtolower(substr(basename(str_replace('\\', '/', get_class($this))), 6));

            throw new \Exception('Failed to parse PHP '.$parserType);
        }
    }

    /**
     * @param array<Token|string> $tokens the tokens to parse
     */
    protected function parse(array &$tokens): bool
    {
        return false;
    }
}
