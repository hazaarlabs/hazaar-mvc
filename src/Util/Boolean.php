<?php

declare(strict_types=1);

namespace Hazaar\Util;

/**
 * Boolean utility class.
 *
 * This class provides a number of helper functions for working with boolean values.
 */
class Boolean
{
    /**
     * Normalize boolean values.
     *
     * This helper function will take a string representation of a boolean such as 't', 'true', 'yes', 'ok' and
     * return a boolean type value.
     *
     * @param mixed $value The string representation of the boolean
     */
    public static function from(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = self::toString($value);
        if ('true' == $value) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve string value of boolean.
     *
     * Normalise boolean string to 'true' or 'false' based on various boolean representations
     *
     * @param mixed $value The string representation of the boolean
     */
    public static function toString(mixed $value): string
    {
        if (false === $value || is_array($value) || null === $value) {
            return 'false';
        }
        if (true === $value) {
            return 'true';
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ('t' == $value
                || 'true' == $value
                || 'on' == $value
                || 'yes' == $value
                || 'y' == $value
                || 'ok' == $value
                || '1' == $value) {
                return 'true';
            }
            if (preg_match('/(\!|not)\s*null/', $value)) {
                return 'true';
            }
        } elseif (is_int($value)) {
            if (0 != (int) $value) {
                return 'true';
            }
        }

        return 'false';
    }

    /**
     * Test whether a value is a boolean.
     *
     * Checks for various representations of a boolean, including strings of 'true/false' and 'yes/no'.
     *
     * @return bool
     */
    public static function is(mixed $value)
    {
        if (!is_string($value)) {
            return is_bool($value);
        }
        $accepted = [
            't',
            'true',
            'f',
            'false',
            'y',
            'yes',
            'n',
            'no',
            'on',
            'off',
        ];

        return in_array(strtolower(trim($value)), $accepted);
    }

    /**
     * The Yes/No function.
     *
     * Simply returns Yes or No based on a boolean value.
     */
    public static function yn(mixed $value, string $trueValue = 'Yes', string $falseValue = 'No'): string
    {
        return self::from($value) ? $trueValue : $falseValue;
    }
}
