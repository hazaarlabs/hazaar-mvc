<?php

namespace Hazaar\Parser;

/**
 * The docblock parser class
 *
 * This class can be used to parse text comments in docblock format into an array
 * of tags and their values.
 *
 * @since 2.0.0
 * 
 * @package     Parser
 *
 */
class DocBlock {

    /**
     * Tags in the docblock that have a whitepace-delimited number of parameters
     * (such as `@param type var desc` and `@return type desc`) and the names of
     * those parameters.
     *
     * @type Array
     */
    public static $vectors = array(
        'param'     => array(
            'key'    => 'var',
            'fields' => array(
                'type',
                'var',
                'desc'
            )
        ),
        'return'    => array(
            'type',
            'desc'
        ),
        'var'       => array(
            'key'    => 'var',
            'fields' => array(
                'type',
                'var',
                'desc'
            )
        ),
        'exception' => array(
            'type',
            'desc'
        )
    );

    /**
     * The brief description from the docblock
     *
     * @type string
     */
    public $brief;

    /**
     * The long detailed description from the docblock
     *
     * @type string
     */
    public $detail;

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
     * @type Array
     */
    public $tags;

    /**
     * The entire DocBlock comment that was parsed.
     *
     * @type String
     */
    public $comment;

    /**
     * CONSTRUCTOR.
     *
     * @param String $comment The text of the docblock
     */
    public function __construct($comment = NULL) {

        if($comment)
            $this->setComment($comment);

    }

    /**
     * Set and parse the docblock comment.
     *
     * @param String $comment The docblock
     */
    public function setComment($comment) {

        $this->brief = '';

        $this->detail = '';

        $this->tags = array();

        $this->comment = $comment;

        $this->parseComment($comment);

    }

    /**
     * Parse the comment into the component parts and set the state of the object.
     *
     * @param  String $comment The docblock.
     */
    protected function parseComment($comment) {

        // Strip the opening and closing tags of the docblock
        $comment = substr($comment, 3, -2);

        // Split into arrays of lines
        $comment = preg_split('/\r?\n\r?/', $comment);

        // Trim asterisks and a single whitespace from the beginning and whitespace from the end of lines
        $comment = array_map(function ($line) {

            return preg_replace('/^\s*\*\s?/', '', rtrim($line));

        }, $comment);

        // Group the lines together by @tags
        $blocks = array();

        $b = -1;

        foreach($comment as $line) {

            if(self::isTagged($line)) {

                $b++;

                $blocks[] = array();

            } else if($b == -1) {

                $b = 0;

                $blocks[] = array();

            }

            $blocks[$b][] = $line;

        }

        // Parse the blocks
        foreach($blocks as $block => $body) {

            $body = implode("\n", $body);

            if($block == 0 && ! self::isTagged($body)) {

                $this->setDescription($body);

                continue;

            } else {

                // This block is tagged
                $tag = substr(self::strTag($body), 1);

                $body = str_repeat(' ', strlen($tag) + 1) . substr($body, strlen($tag) + 1);

                if(isset(self::$vectors[$tag])) {

                    $body = trim($body);

                    $fields = (isset(self::$vectors[$tag]['fields']) ? self::$vectors[$tag]['fields'] : self::$vectors[$tag]);

                    // The tagged block is a vector
                    $count = count($fields);

                    if($body) {

                        $parts = preg_split('/\s+/', $body, $count);

                    } else {

                        $parts = array();

                    }

                    // Default the trailing values
                    $parts = array_pad($parts, $count, NULL);

                    // Store as a mapped array
                    $array = array_combine($fields, $parts);

                    if(isset(self::$vectors[$tag]['key']) && $key = $array[self::$vectors[$tag]['key']]) {

                        $this->tags[$tag][$key] = $array;

                    } else {

                        $this->tags[$tag][] = $array;

                    }

                } elseif($tag == 'brief') {

                    $this->brief = $this->trimTextBlock(trim($body));

                } elseif($tag == 'detail') {

                    $this->detail = $this->trimTextBlock($body);

                } else {

                    // The tagged block is only text
                    $this->tags[$tag][] = $this->trimTextBlock($body);

                }

            }

        }

    }

    private function trimTextBlock($string) {

        if(preg_match('/^\s*/', $string, $matches)) {

            if(($indent = strlen($matches[0])) > 0) {

                $string = preg_replace('/^ {1,' . $indent . '}/m', '', $string);

            }

        }

        return trim($string);

    }

    /**
     * Parse the description block.
     *
     * This block can be either a single line, which will be used as the brief
     * description.  Anything after the first line is used as the detailed description.
     *
     * @param   String $body The description block.
     */
    protected function setDescription($body) {

        if(strlen($body) > 0) {

            $split = preg_split('/\n/', ltrim($body, "\n"), 2);

            $this->brief = trim($split[0]);

            if(count($split) > 1) {

                $this->detail = trim($split[1]);

            }

        }

    }

    /**
     * Whether or not a docblock contains a given @tag.
     *
     * @param  String $tag The name of the @tag to check for
     *
     * @return bool
     */
    public function hasTag($tag) {

        return is_array($this->tags) && array_key_exists($tag, $this->tags);

    }

    /**
     * The value of a tag
     *
     * @param  String $tag
     *
     * @return Array
     */
    public function tag($tag) {

        return $this->hasTag($tag) ? $this->tags[$tag] : NULL;

    }

    /**
     * The value of a tag (concatenated for multiple values)
     *
     * @param  String $tag
     *
     * @param  string $sep The seperator for concatenating
     *
     * @return String
     */
    public function tagImplode($tag, $sep = ' ') {

        return $this->hasTag($tag) ? implode($sep, $this->tags[$tag]) : NULL;

    }

    /**
     * The value of a tag (merged recursively)
     *
     * @param  String $tag
     *
     * @return Array
     */
    public function tagMerge($tag) {

        return $this->hasTag($tag) ? array_merge_recursive($this->tags[$tag]) : NULL;

    }

    /*
     * ==================================
     */

    /**
     * Whether or not a string begins with a @tag
     *
     * @param  String $str
     *
     * @return bool
     */
    public static function isTagged($str) {

        return isset($str[1]) && $str[0] == '@' && ctype_alpha($str[1]);

    }

    /**
     * The tag at the beginning of a string
     *
     * @param  String $str
     *
     * @return String|null
     */
    public static function strTag($str) {

        if(preg_match('/^@[a-z0-9_]+/', $str, $matches))
            return $matches[0];

        return NULL;

    }

    public function toArray() {

        return array(
            'brief'   => $this->brief,
            'detail'  => $this->detail,
            'tags'    => $this->tags,
            'comment' => $this->comment
        );

    }

}