<?php

declare(strict_types=1);

namespace Hazaar\Util;

class Arr
{
    /**
     * Get a value from an array or object with advanced search capabilities.
     *
     * Returns a value from an array or a property from an object, if it exists, using advanced search capabilities
     * such as dot-notation and search parameters.
     *
     * Keys may be specified using dot-notation.  This allows `array_get()` to be called only once instead of for each
     * element in a reference chain.  For example, you can call `array_get($myarray, 'object.child.other');` and each
     * reference will be recursed into if it exists.  If at any step the child does not exist then execution will stop
     * and return null.
     *
     * If the key contains round or square brackets, then this is taken as a search parameter, allowing the specified
     * element to be search for child elements that match the search criteria.  This search parameter can, and is
     * actually designed to, be used with dot-notation.  So for example, you can call `array_get($myarray, 'items(type.id=1).name')`
     * to find an element in the `items` sub-element of `$myarray` that has it's own `type` element with another
     * sub-element of `id` with a value that matches `1`.  As you can imagine, this allows quite a powerful way of accessing
     * sub-elements of arrays/objects using a simple dot-notation search parameter.
     *
     * If the key contains square brackets, then this is taken as an indexed array search parameter and the value will be
     * accessed using it's numeric index.  For example: `array_get($myarray, 'items[0].name')` will return the name of the
     * first item in the `items` array.
     *
     * Support types for `$key` parameter:
     * * _string_ - Single key.
     * * _string_ - Dot notation key for decending into multi-dimensional arrays/objects, including search parameters.
     *
     * @param array<mixed>|\ArrayAccess<string,mixed> $array The array to search.  Objects with public properties are also supported.
     * @param string                                  $key   the array key or object property name to look for
     *
     * @return mixed The value if it exists in the array. Returns the default if it does not. Default is null
     *               if no other default is specified.
     */
    public static function get(array|\ArrayAccess $array, string $key): mixed
    {
        if (!preg_match('/[.\[\(]/', $key)) {
            return $array[$key] ?? null;
        }
        $parts = preg_split('/\.(?![^([]*[\)\]])/', $key);
        foreach ($parts as $part) {
            if (!preg_match('/^(\w+)([\(\[])([\w\d.=\s"\']+)[\)\]]$/', $part, $matches)) {
                $array = $array[$part] ?? null;

                continue;
            }
            if (!(($array = $array[$matches[1]] ?? null)
                && (is_array($array) || $array instanceof \stdClass || $array instanceof \ArrayAccess))) {
                break;
            }
            if (false === strpos($matches[3], '=')) {
                $item = is_numeric($matches[3]) ? (int) $matches[3] : $matches[3];
                if (!array_key_exists($item, $array)) {
                    break;
                }
                $array = $array[$item];
            } else {
                [$item, $criteria] = explode('=', $matches[3]);
                if (('"' === $criteria[0] || "'" === $criteria[0]) && $criteria[0] === substr($criteria, -1)) {
                    $criteria = trim($criteria, '"\'');
                } elseif (strpos($criteria, '.')) {
                    $criteria = floatval($criteria);
                } elseif (is_numeric($criteria)) {
                    $criteria = (int) $criteria;
                }
                foreach ($array as $elem) {
                    $matchValue = self::get($elem, $item);
                    if ($criteria === $matchValue) {
                        $array = $elem;

                        break;
                    }
                }
            }
        }

        return $array;
    }

    /**
     * Array Key Rename.
     *
     * Rename a key in an array to something else, maintaining the value.
     *
     * @param array<mixed>|object $array   the array to work on
     * @param mixed               $keyFrom the key name to rename
     * @param mixed               $keyTo   the key name to change to
     */
    public static function replaceKey(array|object &$array, mixed $keyFrom, mixed $keyTo): mixed
    {
        if (is_array($array)) {
            if (array_key_exists($keyFrom, $array)) {
                $array[$keyTo] = $array[$keyFrom];
                unset($array[$keyFrom]);
            }
        } elseif (is_object($array)) {
            if (property_exists($array, $keyFrom)) {
                $array->{$keyTo } = $array->{$keyFrom };
                unset($array->{$keyFrom });
            }
        }

        return $array;
    }

    /**
     * Test of array is multi-dimensional.
     *
     * Test an array to see if it's a multidimensional array and returns TRUE or FALSE.
     *
     * @param array<mixed> $array The array to test
     */
    public static function isMulti(array $array): bool
    {
        foreach ($array as $a) {
            if (is_array($a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test of array is an associative array.
     *
     * Test an array to see if it is associative or numerically keyed. Returns TRUE for associative or FALSE
     * for numeric.
     *
     * @param array<mixed> $array The array to test
     */
    public static function isAssoc(array $array): bool
    {
        return (bool) count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * Flattens a multidimensional array into a string representation.
     *
     * This method will convert an array into a string representation of the array.  This is useful for storing
     * arrays in a database or other storage where the array needs to be stored as a string.
     *
     * Use the `Arr::unflatten()` method to convert the string back into an array.
     *
     * @param array<mixed> $array        the array to flatten
     * @param string       $delim        The delimiter to use between the key and value of each item. Default is '='.
     * @param string       $sectionDelim The delimiter to use between each item. Default is ';'.
     *
     * @return string the flattened string representation of the array, or null if the input is not an array
     */
    public static function flatten(array $array, string $delim = '=', string $sectionDelim = ';'): string
    {
        $items = [];
        foreach ($array as $key => $value) {
            $items[] = $key.$delim.$value;
        }

        return implode($sectionDelim, $items);
    }

    /**
     * Converts a flattened string or array into a multidimensional array.
     *
     * This method will convert a string representation of an array into an array.  It is normally used in conjunction
     * with the `Arr::flatten()` method to convert the string back into an array, but can also be used to convert a
     * string that contains key-value pairs into an array.
     *
     * @param array<mixed>|string $items        The flattened string to be converted
     * @param string              $delim        The delimiter used to separate the key-value pairs in the flattened string. Default is '='.
     * @param string              $sectionDelim The delimiter used to separate multiple key-value pairs in the flattened string. Default is ';'.
     *
     * @return array<mixed> the resulting multidimensional array
     */
    public static function unflatten(array|string $items, $delim = '=', $sectionDelim = ';'): array
    {
        if (!is_array($items)) {
            $items = preg_split("/\\s*\\{$sectionDelim }\\s*/", trim($items));
        }
        $result = [];
        foreach ($items as $item) {
            $parts = preg_split("/\\s*\\{$delim}\\s*/", $item, 2);
            if (count($parts) > 1) {
                [$key, $value] = $parts;
                $result[$key] = trim($value);
            } else {
                $result[] = trim($parts[0]);
            }
        }

        return $result;
    }

    /**
     * Collate a multi-dimensional array into an associative array where $keyItem is the key and $valueItem is the value.
     *
     * * If the key value does not exist in the array, the element is skipped.
     * * If the value item does not exist, the value will be NULL.
     *
     * @param array<mixed>    $array     the array to collate
     * @param bool|int|string $keyItem   the value to use as the key.  If true is passed, the key will be the array key.
     * @param int|string      $valueItem The value to use as the value.  If not supplied, the whole element will be the value.  Allows re-keying a mult-dimensional array by an array element.
     * @param int|string      $groupItem optional value to group items by
     *
     * @return array<mixed>
     */
    public static function collate(
        array $array,
        bool|int|string $keyItem,
        null|int|string $valueItem = null,
        null|int|string $groupItem = null
    ): array {
        $result = [];
        foreach ($array as $key => $item) {
            if (is_array($item) || $item instanceof \ArrayAccess) {
                if (true === $keyItem) {
                    $result[$key] = $item[$valueItem] ?? null;

                    continue;
                }
                if (!isset($item[$keyItem])) {
                    continue;
                }
                if (null !== $groupItem) {
                    $result[$item[$groupItem] ?? null][$item[$keyItem]] = $item[$valueItem] ?? null;
                } else {
                    $result[$item[$keyItem]] = (null === $valueItem) ? $item : $item[$valueItem] ?? null;
                }
            } elseif ($item instanceof \stdClass) {
                if (true === $keyItem) {
                    $result[$key] = $item[$valueItem] ?? null;

                    continue;
                }
                if (!isset($item->{$keyItem})) {
                    continue;
                }
                if (null !== $groupItem) {
                    $result[$item[$groupItem] ?? null][$item->{$keyItem}] = $item[$valueItem] ?? null;
                } else {
                    $result[$item->{$keyItem}] = (null === $valueItem) ? $item : $item[$valueItem] ?? null;
                }
            }
        }

        return $result;
    }

    /**
     * Converts a multi dimensional array into key[key][key] => value syntax that can be used in html INPUT field names.
     *
     * @param array<mixed> $array The array to convert
     * @param bool         $root  If true, the root element is not included in the key
     *
     * @return array<string, mixed>
     */
    public static function buildHtml(array $array, bool $root = true): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = self::buildHtml($value, false);
                foreach ($value as $skey => $svalue) {
                    $newkey = $key.($root ? "[{$skey}]" : "][{$skey}");
                    $result[$newkey] = $svalue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert a multi-dimenstional array to dot notation.
     *
     * Converts/reduces a multidimensional array into a single dimensional array with keys in dot-notation.
     *
     * @param array<mixed> $array                  the array to convert
     * @param string       $separator              The separater to use between keys.  Defaults to '.', hence the name of the functions.
     * @param int          $depth                  Limit to the specified depth. Starting at 1, this is the number of levels to return.
     *                                             Essentially, this is the number of dots, plus one.
     * @param string       $numericArraySeparators This parameter is used to display numeric arrays. It defaults to '[]' which
     *                                             means that numeric arrays will appear as "item[index].key".  This argument must be at least two
     *                                             characters.  The first character is the left side and the second character is the right side.  Any
     *                                             non-string values or string values less than 2 characters long will be ignored and numeric arrays
     *                                             will not be used.  To disable numeric arrays and cause elements with a numeric key to be output
     *                                             the same as other string key elements, simply set this to NULL.
     *
     * @return array<string,string>
     */
    public static function toDotNotation(
        array $array,
        string $separator = '.',
        ?int $depth = null,
        string $numericArraySeparators = '[]'
    ): array {
        if (!(null === $depth || $depth > 1)) {
            return $array;
        }
        $rows = [];
        $numericArray = (strlen($numericArraySeparators) >= 2);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $children = self::toDotNotation($value, $separator, is_null($depth) ? $depth : ($depth - 1), $numericArraySeparators);
                foreach ($children as $childkey => $child) {
                    if ($numericArray && is_numeric($key)) {
                        $newKey = $numericArraySeparators[0].$key.$numericArraySeparators[1];
                    } else {
                        $newKey = $key;
                    }
                    if ($numericArray && is_numeric($childkey)) {
                        $newKey .= $numericArraySeparators[0].$childkey.$numericArraySeparators[1];
                    } elseif ($numericArray && $childkey[0] === $numericArraySeparators[0]) {
                        $newKey .= $childkey;
                    } else {
                        $newKey .= $separator.$childkey;
                    }
                    $rows[$newKey] = $child;
                }
            } else {
                $rows[$key] = $value;
            }
        }

        return $rows;
    }

    /**
     * Convert a single dimension array in dot notation into a multi-dimensional array.
     *
     * This is the inverse of `Arr::toDotNotation()`.
     *
     * @param array<string,string> $array
     *
     * @return array<mixed>
     */
    public static function fromDotNotation(array $array): array
    {
        /**
         * @var array<mixed> $new
         */
        $new = [];
        foreach ($array as $idx => $value) {
            $parts = explode('.', $idx);
            $cur = &$new;
            foreach ($parts as $part) {
                if (!is_array($cur)) {
                    throw new \Exception('Invalid array structure');
                }
                if (']' === substr($part, -1) && ($pos = strpos($part, '[')) > 0) {
                    if (!preg_match_all('/\[([\w\d]+)\]/', substr($part, $pos), $matches)) {
                        continue;
                    }
                    $key = substr($part, 0, $pos);
                    if (false === array_key_exists($key, $cur)) {
                        $cur[$key] = [];
                    }
                    $cur = &$cur[$key];
                    foreach ($matches[1] as $match) {
                        if (is_numeric($match)) {
                            settype($match, 'int');
                        }
                        if (false === array_key_exists($match, $cur)) {
                            $cur[$match] = [];
                        }
                        if (is_array($cur)) {
                            $cur = &$cur[$match];
                        }
                    }
                } else {
                    if (false === array_key_exists($part, $cur)) {
                        $cur[$part] = [];
                    }
                    $cur = &$cur[$part];
                }
            }
            $cur = $value;
        }

        return $new;
    }

    /**
     * Merge two arrays together but only add elements that don't already exist in the target array.
     *
     * This function is similar to array_merge() but will only add elements that don't already exist in the target array.
     *
     * @param array<mixed> $targetArray The array to merge into
     * @param array<mixed> $sourceArray The array to merge from
     *
     * @return array<mixed> The merged array
     */
    public static function enhance(array $targetArray, array $sourceArray): array
    {
        return array_merge($targetArray, array_diff_key($sourceArray, $targetArray));
    }

    /**
     * Seek the array cursor forward $count number of elements.
     *
     * @param array<mixed> $array The array to seek
     * @param int          $count The number of elements to seek forward
     */
    public static function seek(array &$array, int $count): void
    {
        if (!$count > 0) {
            return;
        }
        for ($i = 0; $i < $count; ++$i) {
            next($array);
        }
    }

    /**
     * Replaces elements from passed arrays or objects into the first array or object recursively.
     *
     * NOTE: This function is almost identical to the PHP function array_replace_recursive() except that it
     * also works with stdClass objects.
     *
     * replace_recursive() replaces the values of item1 with the same values from all the following
     * items. If a key from the first item exists in the second item, its value will be replaced by
     * the value from the second item. If the key exists in the second item, and not the first, it will
     * be created in the first item. If a key only exists in the first item, it will be left as is. If
     * several items are passed for replacement, they will be processed in order, the later item overwriting
     * the previous values.
     *
     * replace_recursive() is recursive : it will recurse into item and apply the same process to the inner value.
     *
     * When the value in item1 is scalar, it will be replaced by the value in item2, may it be scalar, array
     * or stdClass. When the value in item1 and item2 are both arrays or objects, replace_recursive() will replace
     * their respective value recursively.
     */
    public static function replaceRecursive(mixed ...$items): mixed
    {
        if (!($target = array_shift($items))) {
            $target = new \stdClass();
        }

        foreach ($items as $item) {
            if (!((is_array($item) && count($item) > 0)
                || ($item instanceof \stdClass && count(get_object_vars($item)) > 0))) {
                continue;
            }
            foreach ($item as $key => $value) {
                if (is_array($target)) {
                    // To recurse, both the source and target have to be an array/object, otherwise target is replaced.
                    if (array_key_exists($key, $target)
                        && ((is_array($target[$key]) || $target[$key] instanceof \stdClass)
                            && (is_array($value) || $value instanceof \stdClass))) {
                        $target[$key] = self::replaceRecursive($target[$key] ?? null, $value);
                    } else {
                        $target[$key] = $value;
                    }
                } elseif ($target instanceof \stdClass) {
                    // To recurse, both the source and target have to be an array/object, otherwise target is replaced.
                    if (property_exists($target, $key)
                        && ((is_array($target[$key] ?? null) || $target->{$key} instanceof \stdClass)
                            && (is_array($value) || $value instanceof \stdClass))) {
                        $target->{$key} = self::replaceRecursive($target->{$key}, $value);
                    } else {
                        $target->{$key} = $value;
                    }
                }
            }
        }

        return $target;
    }

    /**
     * Recursivly computes the difference of arrays with additional index check.
     *
     * Compares `array1` against `array2` and returns the difference. Unlike array_diff() the array keys are also used
     * in the comparison.  Also, unlike the PHP array_diff_assoc() function, this function recurse into child arrays.
     *
     * @param mixed $arrays more arrays to compare against
     *
     * @return array<mixed>
     */
    public static function diffAssocRecursive(mixed ...$arrays): array
    {
        $array1 = array_shift($arrays);

        /**
         * @var array<mixed> $diff
         */
        $diff = [];
        foreach ($array1 as $key => $value) {
            /**
             * @var array<mixed>|\stdClass $arrayCompare
             */
            foreach ($arrays as $arrayCompare) {
                // Check if the value exists in the compare array and if not, check the next array
                if ((is_array($arrayCompare) && !array_key_exists($key, $arrayCompare))
                    || ($arrayCompare instanceof \stdClass && !property_exists($arrayCompare, $key))) {
                    continue;
                }
                if (!(is_array($value) || $value instanceof \stdClass) && $value !== ($arrayCompare[$key] ?? null)) {
                    continue;
                }
                if (is_array($value) || $value instanceof \stdClass) {
                    $compareValue = $arrayCompare[$key] ?? null;
                    if (!(is_array($compareValue) || $compareValue instanceof \stdClass)) {
                        break;
                    }
                    $childDiff = self::diffAssocRecursive($value, $compareValue);
                    if (!empty($childDiff)) {
                        $value = $childDiff;

                        break;
                    }
                }

                continue 2;
            }
            $diff[$key] = $value;
        }

        return $diff;
    }

    /**
     * Recursively convert an object into an array.
     *
     * This is basically a recursive version of PHP's get_object_vars().
     *
     * @param object $object the object to convert
     *
     * @return array<mixed> returns the converted object as an array or the $object parameter if it is not an object
     */
    public static function fromObject(object $object): array
    {
        $array = get_object_vars($object);
        foreach ($array as &$value) {
            if (is_object($value) || is_array($value)) {
                $value = self::fromObject($value);
            }
        }

        return $array;
    }

    /**
     * Recursively convert an array into an object.
     *
     * This is the inverse of object_to_array().
     *
     * @param array<mixed> $array
     *
     * @return \stdClass returns the converted array as a \stdClass object or false on failure
     */
    public static function toObject(array $array): \stdClass
    {
        $object = new \stdClass();
        foreach ($array as $key => $value) {
            $object->{$key} = is_array($value) ? self::toObject($value) : $value;
        }

        return $object;
    }

    /**
     * Searches the array using a callback function and returns the first corresponding key if successful.
     *
     * @param array<mixed> $haystack the array
     * @param callable     $callback The callback function to use.  This function should return true if the value matches.
     */
    public static function usearch(array $haystack, callable $callback): mixed
    {
        foreach ($haystack as $key => $value) {
            if (true === $callback($value, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Checks if a value exists in an array using a callback function.
     *
     * @param mixed    $haystack the array
     * @param callable $callback The callback function to use.  This function should return true if the value matches.
     *
     * @return bool true if the value is found in the array, false otherwise
     */
    public static function inUarray($haystack, callable $callback)
    {
        return false !== self::usearch($haystack, $callback);
    }

    /**
     * Recursively remove all empty values from an array.
     *
     * Removes all values from an array that are considered empty.  This includes null values, empty strings and empty arrays.
     *
     * Unlike PHP's `empty()` function, this DOES NOT include 0, 0.0, "0" or false.
     *
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    public static function removeEmpty(array &$array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                self::removeEmpty($value);
            }
            if (null === $value
                || (is_string($value) && '' === trim($value))
                || (is_array($value) && 0 === count($value))) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Pull an item out of an array by is key.
     *
     * This function is similar to array_pop() and array_shift(), except that instead of popping the last/first element off the
     * array, it pops an element with the specified key.
     *
     * @param array<mixed> $array the array to pull the element from
     * @param int|string   $key   the key of the element
     *
     * @return mixed the element returned from the array
     */
    public static function pull(array &$array, int|string $key): mixed
    {
        if (!array_key_exists($key, $array)) {
            return null;
        }
        $item = $array[$key];
        unset($array[$key]);

        return $item;
    }

    /**
     * Performs a deep clone on an object or array.
     *
     * The standard PHP clone function will only perform a shallow copy of the object.  PHP has implemented the __clone() magic
     * method to allow objects to recursively clone properties.  However, this does not help when you are cloning a \stdClass
     * object.  This function allows you to perform a deep clone of any object, including \stdClass and also clone all it's
     * properties recursively.
     *
     * @param mixed $object The object to clone.  If the parameter is an array then it will be recursed.  If it is anything it simply returned as is.
     *
     * @return mixed the cloned object or array of objects
     */
    public static function deepClone(mixed $object): mixed
    {
        if (!(is_object($object) || is_array($object))) {
            return $object;
        }
        $nObject = is_array($object) ? [] : clone $object;
        foreach ($object as $key => &$property) {
            $nProperty = self::deepClone($property);
            if (is_array($nObject)) {
                $nObject[$key] = $nProperty;
            } elseif (is_object($nObject)) {
                $nObject->{$key} = $nProperty;
            }
        }

        return $nObject;
    }

    /**
     * Computes the difference of arrays using keys for comparison recursively.
     *
     * This is the same as array_diff_key() except it will recurse into child arrays and return
     * them in the result if they contain any key differences.
     *
     * @param mixed ...$arrays More arrays to compare against.
     *
     * @return array<mixed>
     */
    public static function diffKeyRecursive(mixed ...$arrays): array
    {
        $array1 = array_shift($arrays);
        $diff = [];
        foreach ($array1 as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            if ($value instanceof \stdClass) {
                $value = (array) $value;
            }

            foreach ($arrays as $itemCompare) {
                if ($itemCompare instanceof \stdClass) {
                    $itemCompare = (array) $itemCompare;
                } elseif (!is_array($itemCompare)) {
                    continue;
                }
                if (array_key_exists($key, $itemCompare)) {
                    if (!(is_array($value) && is_array($itemCompare[$key]))) {
                        continue 2;
                    }
                    $valueDiff = self::diffKeyRecursive($value, $itemCompare[$key]);
                    if (0 === count($valueDiff)) {
                        continue 2;
                    }
                    $value = $valueDiff;
                }
            }
            $diff[$key] = $value;
        }

        return $diff;
    }

    /**
     * Grammatically correct array implode.
     *
     * Implode an array, joining it's values in a grammatically correct manner.
     *
     * ### Example:
     *
     * `echo grammatical_implode([ 'One', 'Two', 'Three', 'Four' ]);`
     *
     * Output:  One, Two, Three and Four
     *
     * @param array<string> $array
     */
    public static function grammaticalImplode(array $array, string $glue = 'and'): string
    {
        return implode(
            " {$glue} ",
            array_filter(
                array_merge([implode(', ', array_slice($array, 0, -1))], array_slice($array, -1)),
                function (string $value): bool {return strlen($value) > 0; }
            )
        );
    }
}
