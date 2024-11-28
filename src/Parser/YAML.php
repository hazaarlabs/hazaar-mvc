<?php

namespace Hazaar\Parser;

class YAML
{
    public function __construct() {}

    /**
     * Parse a YAML file into an array.
     *
     * @param string $filename The filename of the YAML file to parse
     *
     * @return array<mixed>|false Returns the parsed YAML as an array or false if the file does not exist
     */
    public function parseFile(string $filename): array|false
    {
        if (!\file_exists($filename)) {
            return false;
        }

        return $this->parse(file_get_contents($filename));
    }

    /**
     * Parse a YAML string into an array.
     *
     * @param string $content The YAML content to parse
     *
     * @return array<mixed>
     */
    public function parse(string $content): array
    {
        $yaml = [];
        $iter = explode("\n", $content);
        reset($iter);
        $this->parseSection($iter, $yaml);

        return $yaml;
    }

    /**
     * Parse a YAML section.
     *
     * @param array<mixed> $iter    The iterator
     * @param array<mixed> $parent  The parent array
     * @param int          $level   The level of the section
     * @param array<mixed> $indents The indents
     *
     * @throws \Exception
     */
    private function parseSection(
        array &$iter,
        array &$parent,
        int $level = 0,
        array &$indents = []
    ): void {
        do {
            $item = current($iter);

            if (($pos = strpos($item, ':')) === false) {
                if (false === strpos($item, '-')) {
                    throw new \Exception('Invalid YAML!');
                }
                $listKey = key($parent);
                if (!is_array($parent[$listKey])) {
                    $parent[$listKey] = [];
                }
                if (preg_match('/(\s*)\-\s+(\w+)/', $item, $matches)) {
                    $parent[$listKey][] = $matches[2];
                }
            } else {
                $key = substr($item, 0, $pos);
                $value = trim(substr($item, $pos + 1));
                if (($len = strpos($key, '-')) !== false) {
                    $key = trim(substr($key, $len + 1));
                    $listKey = key($parent);
                    if (\array_key_exists($level, $indents) && $len === $indents[$level]) {
                        $parent[$key] = $value;
                        end($parent);
                    } elseif (\array_key_exists($level, $indents) && $len < $indents[$level]) {
                        prev($iter);

                        return;
                    } else {
                        if (!is_array($parent[$listKey])) {
                            $parent[$listKey] = [];
                        }
                        $indents[++$level] = $len;
                        $this->parseSection($iter, $parent[$listKey], $level, $indents);
                        unset($indents[$level--]);
                    }
                } elseif ($level > 0) {
                    prev($iter);

                    return;
                } else {
                    $parent[$key] = $value;
                    end($parent);
                }
            }
        } while (next($iter));
    }
}
