<?php

declare(strict_types=1);

namespace Hazaar\Parser;

/**
 * The docblock parser class.
 *
 * This class can be used to parse text comments in docblock format into an array
 * of tags and their values.
 *
 * @since 2.0.0
 */
class DocBlock
{
    /**
     * Tags in the docblock that have a whitepace-delimited number of parameters
     * (such as `@param type var desc` and `@return type desc`) and the names of
     * those parameters.
     *
     * @var array{param: array{fields: array<string>}, return: array<string>, var: array{key: string, fields: array<string>}, exception: array<string>}
     */
    private static array $vectors = [
        'param' => [
            'fields' => [
                'type',
                'var',
                'desc',
            ],
        ],
        'return' => [
            'type',
            'desc',
        ],
        'var' => [
            'key' => 'var',
            'fields' => [
                'type',
                'var',
                'desc',
            ],
        ],
        'exception' => [
            'type',
            'desc',
        ],
    ];

    /**
     * The brief description from the docblock.
     */
    private ?string $brief = null;

    /**
     * The long detailed description from the docblock.
     */
    private ?string $detail = null;

    /**
     * The tags defined in the docblock.
     *
     * The array has keys which are the tag names (excluding the @) and values
     * that are arrays, each of which is an entry for the tag.
     *
     * In the case where the tag name is defined in {@see DocBlock::$vectors} the
     * value within the tag-value array is an array in itself with keys as
     * described by {@see DocBlock::$vectors}.
     *
     * @var array<array<string>>
     */
    private array $tags;

    /**
     * CONSTRUCTOR.
     *
     * @param string $comment The text of the docblock
     */
    public function __construct($comment = null)
    {
        if (!$comment) {
            return;
        }
        $this->setComment($comment);
    }

    /**
     * Set and parse the docblock comment.
     *
     * @param string $comment The docblock
     */
    public function setComment(string $comment): void
    {
        $this->brief = null;
        $this->detail = null;
        $this->tags = [];
        $this->parseComment($comment);
    }

    /**
     * Whether or not a docblock contains a given @tag.
     *
     * @param string $tag The name of the @tag to check for
     *
     * @return bool
     */
    public function hasTag(string $tag)
    {
        return array_key_exists($tag, $this->tags);
    }

    /**
     * The value of a tag.
     *
     * @return null|array<mixed>
     */
    public function tag(string $tag): ?array
    {
        return $this->hasTag($tag) ? $this->tags[$tag] : null;
    }

    /**
     * The value of a tag (concatenated for multiple values).
     *
     * @param string $sep The seperator for concatenating
     */
    public function tagImplode(string $tag, string $sep = ' '): ?string
    {
        return $this->hasTag($tag) ? implode($sep, $this->tags[$tag]) : null;
    }

    /**
     * The value of a tag (merged recursively).
     *
     * @return array<string>
     */
    public function tagMerge(string $tag): ?array
    {
        return $this->hasTag($tag) ? array_merge_recursive($this->tags[$tag]) : null;
    }

    /**
     * Return the parsed DocBlock as a nice friendly array.
     *
     * @return array{brief: string, detail: string, tags: array<array<string>>}
     */
    public function toArray(): array
    {
        return [
            'brief' => $this->brief,
            'detail' => $this->detail,
            'tags' => $this->tags,
        ];
    }

    /**
     * Return the brief comment if set.
     */
    public function brief(): ?string
    {
        return $this->brief;
    }

    /**
     * Return the detailed comment if set.
     */
    public function detail(): ?string
    {
        return $this->detail;
    }

    /**
     * Parse the comment into the component parts and set the state of the object.
     *
     * @param string $comment the docblock
     */
    protected function parseComment(string $comment): void
    {
        // Strip the opening and closing tags of the docblock
        $comment = trim(substr($comment, 3, -2));
        // Split into arrays of lines
        $comment = preg_split('/\r?\n\r?/', $comment);
        // Trim asterisks and a single whitespace from the beginning and whitespace from the end of lines
        $comment = array_map(function ($line) {
            return preg_replace('/^\s*\*\s?/', '', rtrim($line));
        }, $comment);
        // Group the lines together by @tags
        $blocks = [];
        $b = -1;
        foreach ($comment as $line) {
            if (self::isTagged($line)) {
                ++$b;
                $blocks[] = [];
            } elseif (-1 == $b) {
                $b = 0;
                $blocks[] = [];
            }
            $blocks[$b][] = $line;
        }
        // Parse the blocks
        foreach ($blocks as $block => $body) {
            $body = implode("\n", $body);
            if (0 == $block && !self::isTagged($body)) {
                $this->setDescription($body);

                continue;
            }
            // This block is tagged
            $tag = substr(self::strTag($body), 1);
            $body = str_repeat(' ', strlen($tag) + 1).substr($body, strlen($tag) + 1);
            if (isset(self::$vectors[$tag])) {
                $body = preg_replace('/[\n\s]+/', ' ', trim($body));
                $fields = (isset(self::$vectors[$tag]['fields']) ? self::$vectors[$tag]['fields'] : self::$vectors[$tag]);
                // The tagged block is a vector
                $count = count($fields);
                if ($body) {
                    $parts = preg_split('/\s+/', $body, $count);
                } else {
                    $parts = [];
                }
                // Default the trailing values
                $parts = array_pad($parts, $count, null);
                // Store as a mapped array
                $array = array_combine($fields, $parts);
                if (isset(self::$vectors[$tag]['key']) && $key = $array[self::$vectors[$tag]['key']]) {
                    if ('$' === $key[0]) {
                        $key = substr($key, 1);
                    }
                    $this->tags[$tag][$key] = $array;
                } else {
                    $this->tags[$tag][] = $array;
                }
            } elseif ('brief' == $tag) {
                $this->brief = $this->trimTextBlock(trim($body));
            } elseif ('detail' == $tag) {
                $this->detail = $this->trimTextBlock($body);
            } else {
                // The tagged block is only text
                $this->tags[$tag][] = $this->trimTextBlock($body);
            }
        }
    }

    /**
     * Parse the description block.
     *
     * This block can be either a single line, which will be used as the brief
     * description.  Anything after the first line is used as the detailed description.
     *
     * @param string $body the description block
     */
    protected function setDescription(string $body): void
    {
        if (strlen($body) > 0) {
            $split = preg_split('/\n/', ltrim($body, "\n"), 2);
            $this->brief = trim($split[0]);
            if (count($split) > 1) {
                $this->detail = trim($split[1]);
            }
        }
    }

    private function trimTextBlock(string $string): string
    {
        if (preg_match('/^\s*/', $string, $matches)) {
            if (($indent = strlen($matches[0])) > 0) {
                $string = preg_replace('/^ {1,'.$indent.'}/m', '', $string);
            }
        }

        return trim($string);
    }

    /**
     * Whether or not a string begins with a @tag.
     */
    private static function isTagged(string $str): bool
    {
        return isset($str[1]) && '@' == $str[0] && ctype_alpha($str[1]);
    }

    /**
     * The tag at the beginning of a string.
     */
    private static function strTag(string $str): ?string
    {
        if (preg_match('/^@[a-z0-9_]+/', $str, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
