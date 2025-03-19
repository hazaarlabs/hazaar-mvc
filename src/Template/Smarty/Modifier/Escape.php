<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Escape a string for HTML, URL or quotes.
 */
class Escape
{
    /**
     * @param string $string             The string to escape
     * @param string $format             The format to escape the string to. Can be 'html', 'url' or 'quotes'.
     * @param string $characterEncoding The character encoding to use
     *
     * @return string the escaped string
     */
    public function run(string $string, string $format = 'html', string $characterEncoding = 'ISO-8859-1'): string
    {
        if ('html' == $format) {
            $string = htmlspecialchars($string, ENT_COMPAT, $characterEncoding);
        } elseif ('url' === $format) {
            $string = urlencode($string);
        } elseif ('quotes' === $format) {
            $string = addcslashes($string, "'");
        }

        return $string;
    }
}
