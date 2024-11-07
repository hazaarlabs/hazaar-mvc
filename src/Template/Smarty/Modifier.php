<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty;

use Hazaar\Date;

class Modifier
{
    public function execute(string $name, mixed $value, mixed ...$args): mixed
    {
        if (!method_exists($this, $name)) {
            throw new \Exception('Modifier '.$name.' does not exist!');
        }
        $reflectionMethod = new \ReflectionMethod($this, $name);
        $reflectionParameter = $reflectionMethod->getParameters()[0];
        $type = (string) $reflectionParameter->getType();
        if ('mixed' === $type && null === $value) {
            $type = 'string';
        }
        $value = match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'array' => (array) $value,
            'object' => (object) $value,
            default => $value,
        };

        return $reflectionMethod->invokeArgs($this, array_merge([$value], $args));
    }

    public function capitalize(string $string): string
    {
        return ucwords($string);
    }

    public function cat(): string
    {
        return implode('', func_get_args());
    }

    public function count_characters(string $string, bool $include_whitespace = false): int
    {
        if (false === $include_whitespace) {
            $string = str_replace(' ', '', $string);
        }

        return strlen($string);
    }

    public function count_paragraphs(string $string): int
    {
        return substr_count(trim($string, "\n\n"), "\n\n") + 1;
    }

    /**
     * Counts the number of sentences in a given string.
     *
     * @param string $string the input string to count sentences from
     *
     * @return int the number of sentences in the string
     */
    public function count_sentences(string $string): int
    {
        if (!preg_match_all('/[\.\!\?]+[\s\n]?/', $string, $matches)) {
            return strlen($string) > 0 ? 1 : 0;
        }
        $count = count($matches[0]);
        $final = substr(trim($string), -1);
        if ('.' != $final && '!' != $final && '?' != $final) {
            ++$count;
        }

        return $count;
    }

    /**
     * Counts the number of words in a string.
     *
     * @param string $string the input string
     *
     * @return int the number of words in the string
     */
    public function count_words(string $string): int
    {
        return str_word_count($string);
    }

    /**
     * Formats a date using the specified format.
     *
     * @param mixed       $item   The date to format. Can be a string, integer, or DateTime object.
     * @param null|string $format The format to use for the date. If not provided, the default format '%c' will be used.
     *
     * @return string the formatted date
     */
    public function date_format(mixed $item, ?string $format = null): string
    {
        if (!$item instanceof Date) {
            $item = new Date($item);
        }
        if (!$format) {
            $format = '%c';
        }

        return str_ftime($format, $item->getTimestamp());
    }

    public function default(mixed $value, mixed $default = null): mixed
    {
        if (null !== $value) {
            return $value;
        }

        return $default;
    }

    public function print(mixed $value): string
    {
        return print_r($value, true);
    }

    public function dump(mixed $value): string
    {
        ob_start();
        var_dump($value);
        $output = ob_get_clean();
        if ('<pre' === substr($output, 0, 4)) {
            $offsetCR = strpos($output, "\n") + 1;
            $suffixPRE = strrpos($output, '</pre>');
            $offsetSmallOpen = strpos($output, '<small>', $offsetCR);
            $offsetSmallClose = strpos($output, '</small>', $offsetSmallOpen) + 8;
            $output = substr($output, $offsetSmallClose, $suffixPRE - $offsetSmallClose);
        }

        return trim($output);
    }

    public function export(mixed $value): string
    {
        return var_export($value, true);
    }

    public function type(mixed $value): string
    {
        return gettype($value);
    }

    public function escape(string $string, string $format = 'html', string $character_encoding = 'ISO-8859-1'): string
    {
        if ('html' == $format) {
            $string = htmlspecialchars($string, ENT_COMPAT, $character_encoding);
        } elseif ('url' === $format) {
            $string = urlencode($string);
        } elseif ('quotes' === $format) {
            $string = addcslashes($string, "'");
        }

        return $string;
    }

    public function indent(string $string, int $length = 4, string $pad_string = ' '): string
    {
        return str_pad($string, $length, $pad_string, STR_PAD_LEFT);
    }

    public function lower(string $string): string
    {
        return strtolower($string);
    }

    public function nl2br(string $string): string
    {
        return str_replace("\n", '<br />', $string);
    }

    public function number_format(string $string, int $decimals = 0, string $dec_point = '.', string $thousands_sep = ','): string
    {
        return number_format(floatval($string), $decimals, $dec_point, $thousands_sep);
    }

    public function regex_replace(string $string, string $pattern = '//', string $replacement = ''): string
    {
        return preg_replace($pattern, $replacement, $string);
    }

    public function replace(string $string, string $search, string $replace): string
    {
        return str_replace($search, $replace, $string);
    }

    public function spacify(string $string, string $replacement = ' '): string
    {
        return implode($replacement, preg_split('//', $string));
    }

    public function string_format(string $string, string $format): string
    {
        return sprintf($format, $string);
    }

    public function strip(string $string, string $replacement = ' '): string
    {
        return preg_replace('/[\s\n\t]+/', $replacement, $string);
    }

    public function strip_tags(string $string): string
    {
        return preg_replace('/<[^>]+>/', '', $string);
    }

    public function truncate(string $string, int $chars = 80, string $text = '...', bool $cut = false, bool $middle = false): string
    {
        if (strlen($string) > ($chars -= strlen($text))) {
            if (true === $middle) {
                $string = substr($string, 0, $chars / 2).$text.substr($string, -($chars / 2));
            } else {
                $string = substr($string, 0, $chars);
                if (false === $cut) {
                    $string = substr($string, 0, strrpos($string, ' '));
                }
                $string = $string.$text;
            }
        }

        return $string;
    }

    public function upper(string $string): string
    {
        return strtoupper($string);
    }

    public function wordwrap(string $string, int $width = 80, string $break = "\n", bool $cut = true): string
    {
        return wordwrap($string, $width, $break, $cut);
    }

    public function implode(mixed $array, string $glue = ''): string
    {
        if (is_array($array)) {
            return implode($glue, $array);
        }

        return $array;
    }
}
