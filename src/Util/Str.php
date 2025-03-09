<?php

declare(strict_types=1);

namespace Hazaar\Util;

use Hazaar\Exception\MatchReplaceError;

class Str
{
    /**
     * @var array<string> this is a list of reserved PHP keywords that should not be used as variable names
     */
    private static array $reservedKeywords = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else',
        'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile',
        'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset',
        'list', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'require',
        'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor',
    ];

    /**
     * @var array<string> this is a list of reserved PHP constants that should not be used as variable names
     */
    private static array $reservedConstants = [
        '__CLASS__', '__DIR__', '__FILE__', '__FUNCTION__', '__LINE__',
        '__METHOD__', '__NAMESPACE__', '__TRAIT__',
    ];

    /**
     * Byte to string formatter.
     *
     * Formats an integer representing a size in bytes to a human readable string representation.
     *
     * @param int    $bytes         the byte value to convert to a string
     * @param string $type          The type to convert to. Type can be:
     *                              * B (bytes)
     *                              * K (kilobytes)
     *                              * M (megabytes)
     *                              * G (giabytes)
     *                              * T (terrabytes)
     * @param int    $precision     the number of decimal places to show
     * @param bool   $excludeSuffix if true, the suffix will not be included in the output
     *
     * @return string The human readable byte string. eg: '100 MB'.
     */
    public static function fromBytes(int $bytes, ?string $type = null, ?int $precision = null, bool $excludeSuffix = false)
    {
        if (null === $type) {
            if ($bytes < pow(2, 10)) {
                $type = 'B';
            } elseif ($bytes < pow(2, 20)) {
                $type = 'K';
            } elseif ($bytes < pow(2, 30)) {
                $type = 'M';
            } elseif ($bytes < pow(2, 40)) {
                $type = 'G';
            } elseif ($bytes < pow(2, 50)) {
                $type = 'T';
            } elseif ($bytes < pow(2, 60)) {
                $type = 'P';
            } else {
                $type = 'E';
            }
        }
        $type = strtoupper($type);
        $value = $bytes;
        $suffix = 'bytes';
        $precision = $precision ?? 0;

        switch ($type) {
            case 'K':
                $value = $bytes / pow(2, 10);
                $suffix = 'KB';

                break;

            case 'M':
                $value = $bytes / pow(2, 20);
                $suffix = 'MB';
                $prec = 2;

                break;

            case 'G':
                $value = $bytes / pow(2, 30);
                $suffix = 'GB';

                break;

            case 'T':
                $value = $bytes / pow(2, 40);
                $suffix = 'TB';

                break;

            case 'P':
                $value = $bytes / pow(2, 50);
                $suffix = 'PB';

                break;

            case 'E':
                $value = $bytes / pow(2, 60);
                $suffix = 'EB';

                break;
        }

        return number_format($value, $precision).($excludeSuffix ? '' : $suffix);
    }

    /**
     * String to bytes formatter.
     *
     * Returns an integer value representing a number of bytes from the standard bytes size string supplied.
     *
     * @param string $string The byte string value to convert to an integer. eg: '100MB'
     *
     * @return bool|float The number of bytes or false on failure
     */
    public static function toBytes(string $string): bool|float
    {
        if (preg_match('/([\d\.]+)\s*(\w*)/', $string, $matches)) {
            $size = floatval($matches[1]);

            switch (strtoupper($matches[2])) {
                case 'K':
                case 'KB':
                    $size = round($size * pow(2, 10));

                    break;

                case 'M':
                case 'MB':
                    $size = round($size * pow(2, 20));

                    break;

                case 'G':
                case 'GB':
                    $size = round($size * pow(2, 30));

                    break;

                case 'T':
                case 'TB':
                    $size = round($size * pow(2, 40));

                    break;

                case 'P':
                case 'PB':
                    $size = round($size * pow(2, 50));

                    break;

                case 'E':
                case 'EB':
                    $size = round($size * pow(2, 60));

                    break;
            }

            return $size;
        }

        return false;
    }

    /**
     * Fix a numeric string.
     *
     * Sometimes a numeric (int or float) will be stored as a string variable. This can cause
     * issues with functions that check the variable type to determine what to do with it. This
     * function allows you to simply pass a variable through it and it will 'fix' it. If it is
     * a string and is a numeric value, it will be converted to the appropriate variable type.
     *
     * If the value is a string and is meant to be a string, it will be left alone.
     *
     * @param mixed $value the variable to type check and possibly fix
     *
     * @return float|int the fixed variable
     */
    public static function fixtype(&$value): float|int
    {
        if (is_numeric($value)) {
            /*
             * NOTE: We can't use settype() here as it is unable to distinguish between a float and an int. So we
             * use this trick to force PHP to figure it out for itself. Adding zero will not affect the value
             * but will cause the type to be converted to int or float.
             */
            $value = $value + 0;
        }

        return $value;
    }

    /**
     * Generates a globally unique identifier (GUID) in the format 'xxxxxxxx-yxxx-yxxx-yxxx-xxxxxxxxxxxx'.
     *
     * The GUID is generated using a combination of random numbers and specific formatting rules.
     * The 'x' characters are replaced with random hexadecimal digits (0-9, a-f).
     * The 'y' characters are replaced with random hexadecimal digits from the set (8, 9, a, b).
     *
     * @return string the generated GUID
     */
    public static function guid(): string
    {
        $format = 'xxxxxxxx-yxxx-yxxx-yxxx-xxxxxxxxxxxx';

        return preg_replace_callback('/[xy]/', function ($c) {
            $num = rand(0, 15);
            $value = (('x' == $c[0]) ? $num : ($num & 0x3 | 0x8));

            return dechex($value);
        }, $format);
    }

    /**
     * Perform a regular expression match on a string using multiple possible regular expressions.
     *
     * This is the same as calling preg_match() except that $patterns is an array of regular expressions.
     * Execution returns on the first match.
     *
     * @param array<string>|string $patterns an array of patterns to search for, as a string
     * @param string               $subject  the input string
     * @param array<mixed>         $matches  If matches is provided, then it is filled with the results of search. $matches[0] will contain the text that matched the full pattern, $matches[1] will have the text that matched the first captured parenthesized subpattern, and so on.
     * @param mixed                $flags    For details on available flags, see the [preg_match()](http://php.net/manual/en/function.preg-match.php) documentation.
     * @param int                  $offset   Normally, the search starts from the beginning of the subject string. The optional parameter offset can be used to specify the alternate place from which to start the search (in bytes).
     */
    public static function pregMatchArray(
        array|string $patterns,
        string $subject,
        ?array &$matches = null,
        mixed $flags = '0',
        int $offset = 0
    ): int {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches, $flags, $offset)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Convert an array into a CSV line.
     *
     * @param array<mixed> $input     The array to convert to a CSV line
     * @param string       $delimiter Defaults to comma (,)
     * @param string       $enclosure Defaults to double quote (")
     */
    public static function putCSV(array $input, string $delimiter = ',', string $enclosure = '"', string $escapeChar = '\\'): string
    {
        $fp = fopen('php://temp', 'r+b');
        fputcsv($fp, $input, $delimiter, $enclosure, $escapeChar);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);

        return $data;
    }

    /**
     * Generate a truly random string of characters.
     *
     * @param mixed $length         the length of the random string being created
     * @param mixed $includeSpecial Whether or not special characters.  Normally only Aa-Zz, 0-9 are used.  If TRUE will include
     *                              characters such as #, $, etc.  This can also be a string of characters to use.
     *
     * @return string a totally random string of characters
     */
    public static function random($length, $includeSpecial = false)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (true === $includeSpecial) {
            $characters .= ' ~!@#$%^&*()-_=+[{]}\|;:\'",<.>/?';
        } elseif (is_string($includeSpecial)) {
            $characters .= $includeSpecial;
        }
        $count = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, $count - 1)];
        }

        return $randomString;
    }

    /**
     * Use the match/replace algorithm on a string to replace mustache tags with view data.
     *
     * This is similar code used in the Smarty view template renderer.
     *
     * So strings such as:
     *
     * * ```Hello, {{entity}}``` will replace {{entity}} with the value of ```$data->entity```.
     * * ```The quick brown {{animal.one}}, jumped over the lazy {{animal.two}}``` will replace the tags with values in a multi-dimensional array.
     *
     * @param string $string the string to perform the match/replace on
     * @param mixed  $data   the data to use for matching
     * @param bool   $strict in strict mode, the function will return NULL if any of the matches are do not exist in data or are NULL
     *
     * @return mixed the modified string with mustache tags replaced with view data, or removed if the view data does not exist
     */
    public static function matchReplace(string $string, $data, $strict = false)
    {
        try {
            $string = preg_replace_callback('/\{\{([^\}{2}]*)\}\}/', function ($match) use ($data, $strict) {
                $value = '';
                $key = null;
                if ('?' === substr($match[1], 0, 1)) {
                    [$test, $output] = explode(':', substr($match[1], 1), 2);
                    if ('!' === substr($test, 0, 1)) {
                        $eval = false;
                        $test = substr($test, 1);
                    } else {
                        $eval = true;
                    }
                    if (empty($data[$test] ?? null) !== $eval) {
                        $key = $output;
                    }
                } else {
                    $key = $match[1];
                }
                if ($key) {
                    if ('>' === substr($key, 0, 1)) {
                        $value = substr($key, 1);
                    } else {
                        $value = $data[$key] ?? null;
                    }
                    if (true === $strict && null === $value) {
                        throw new MatchReplaceError();
                    }
                }

                return $value;
            }, $string);
        } catch (MatchReplaceError $e) {
            return null;
        }

        return $string;
    }

    /**
     * Checks if a given word is a reserved keyword or constant.
     *
     * This method converts the input word to both lowercase and uppercase,
     * and checks if it exists in the list of reserved keywords or constants.
     *
     * @param string $word the word to check
     *
     * @return bool true if the word is reserved, false otherwise
     */
    public static function isReserved(string $word): bool
    {
        return in_array(strtolower($word), self::$reservedKeywords)
            || in_array(strtoupper($word), self::$reservedConstants);
    }
}
