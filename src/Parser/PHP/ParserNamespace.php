<?php

declare(strict_types=1);

namespace Hazaar\Parser\PHP;

use Hazaar\Parser\DocBlock;

class ParserNamespace extends TokenParser
{
    use Traits\DocBlockParser;

    public ?DocBlock $docBlock = null;

    /**
     * @var array<ParserConstant>
     */
    public array $constants = [];

    /**
     * @var array<ParserClass>
     */
    public array $classes = [];

    /**
     * @var array<ParserInterface>
     */
    public array $interfaces = [];

    /**
     * @var array<ParserFunction>
     */
    public array $functions = [];

    /**
     * Apply the namespace to the given array of namespaces.
     */
    public function apply(string $namespace): string
    {
        if ('\\' === substr($namespace, 0, 1)) {
            return $namespace;
        }

        return '\\'.$this->name.'\\'.$namespace;
    }

    protected function parse(array &$tokens): bool
    {
        $token = current($tokens);
        if (T_NAMESPACE == $token->type) {
            $namespace = [
                'name' => [],
                'line' => $token->line,
            ];
            $this->docBlock = $this->checkDocComment($tokens);
            while ($token = next($tokens)) {
                if ($token instanceof Token) {
                    if (T_NS_SEPARATOR == $token->type) {
                        continue;
                    }
                    $namespace['name'][] = $token->value;
                } elseif (';' == $token) {
                    $this->name = implode('\\', $namespace['name']);

                    return true;
                }
            }
        }

        return false;
    }
}
