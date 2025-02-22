<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Count the number of sentences in a string.
 */
class CountSentences
{
    /**
     * @param string $string the input string to count sentences from
     *
     * @return int the number of sentences in the string
     */
    public function run(string $string): int
    {
        if (!preg_match_all('/[\.\!\?]+[\s\n]?/', $string, $matches)) {
            return strlen($string) > 0 ? 1 : 0;
        }
        $count = count($matches[0]);
        $final = substr(trim($string), -1);

        return $count + ('.' !== $final && '!' !== $final && '?' !== $final ? 1 : 0);
    }
}
