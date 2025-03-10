<?php

declare(strict_types=1);

namespace Hazaar\Util;

/**
 * Object Utility.
 *
 * This class provides a number of utility functions for working with objects.
 */
class Obj
{
    /**
     * Replaces a property in an object at key with another value.
     *
     * This allows a property in am object to be replaced.  Normally this would not be
     * difficult, unless the target property is an nth level deep object.  This function
     * allows that property to be targeted with a key name in dot-notation.
     *
     * @param \stdClass            $target The target in which the property will be replaced
     * @param array<string>|string $key    A key in either an array or dot-notation
     * @param mixed                $value  The value that will be used as the replacement
     *
     * @return bool True if the value was found and replaced.  False otherwise.
     */
    public static function replaceProperty(\stdClass &$target, array|string $key, mixed $value): bool
    {
        $cur = &$target;
        $parts = is_array($key) ? $key : explode('.', $key);
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (!property_exists($cur, $part)) {
                return false;
            }
            $cur = &$cur->{$part};
        }
        $cur->{$last} = $value;

        return true;
    }

    /**
     * Object Merge.
     *
     * Performs a similar operation to array_merge() except works with objects.  ANY objects. ;)
     *
     * @param mixed ...$objects Takes 2 or more objects to merge together
     *
     * @return object the merged object with the class of the first object argument
     */
    public static function merge(mixed ...$objects): ?object
    {
        if (count($objects) < 2 || !is_object($objects[0])) {
            return null;
        }
        $target_reflection = new \ReflectionObject($objects[0]);
        $target_object = $target_reflection->newInstance();
        foreach ($objects as $object) {
            if (is_object($object)) {
                $reflection = new \ReflectionObject($object);
                foreach ($reflection->getProperties() as $property) {
                    $property->setAccessible(true);
                    $property->setValue($target_object, $property->getValue($object));
                }
            } elseif (is_array($object)) {
                foreach ($object as $key => $value) {
                    $target_object->{$key} = $value;
                }
            }
        }

        return $target_object;
    }
}
