# Utilities

These are useful utility classes and functions that provide features missing from PHP or offer convenient shorthand for common tasks. They are now organized under the [`Hazaar\Util` namespace](/api/class/Hazaar/Util.html). For example, array-related utilities are found in [`Hazaar\Util\Arr`](/api/class/Hazaar/Util/Arr.html).

Hazaar's utility classes are designed to make common programming tasks easier and more expressive. They provide a consistent, object-oriented interface for working with arrays, strings, numbers, dates, objects, and more. If you previously used global helper functions, you will now find their improved and expanded versions in the `Hazaar\Util` namespace. Explore the API documentation for each class to discover all available methods and features.

## Array Utilities ([`Hazaar\Util\Arr`](/api/class/Hazaar/Util/Arr.html))

### [`Hazaar\Util\Arr::get()`](/api/class/Hazaar/Util/Arr.html) - Array Key Exists

The `ake()` function is now replaced by [`Hazaar\Util\Arr::get()`](/api/class/Hazaar/Util/Arr.html). This method allows you to retrieve a value from an array (or object) using advanced search capabilities, including dot-notation and search parameters.

#### Example

```php
use Hazaar\Util\Arr;

$array = ['key' => 'value'];

echo Arr::get($array, 'key'); // Outputs: value
```

You can also use dot-notation for nested arrays:

```php
$array = ['user' => ['name' => 'Jamie']];
echo Arr::get($array, 'user.name'); // Outputs: Jamie
```

For more advanced usage and options, see the [`Arr` class API documentation](/api/class/Hazaar/Util/Arr.html).

### [`Hazaar\Util\Arr::flatten()`](/api/class/Hazaar/Util/Arr.html) and [`Arr::unflatten()`](/api/class/Hazaar/Util/Arr.html)

Flatten a multidimensional array into a string, and convert it back:

```php
$array = ['foo' => 'bar', 'baz' => 'qux'];
$flat = Arr::flatten($array); // foo=bar;baz=qux
$unflat = Arr::unflatten($flat); // ['foo' => 'bar', 'baz' => 'qux']
```

### [`Hazaar\Util\Arr::collate()`](/api/class/Hazaar/Util/Arr.html)

Collate a multidimensional array into an associative array by a key:

```php
$users = [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
];
$collated = Arr::collate($users, 'id', 'name'); // [1 => 'Alice', 2 => 'Bob']
```

### [`Hazaar\Util\Arr::enhance()`](/api/class/Hazaar/Util/Arr.html)

Merge two arrays, only adding elements that don't already exist in the target array:

```php
$a = ['foo' => 1];
$b = ['foo' => 2, 'bar' => 3];
$enhanced = Arr::enhance($a, $b); // ['foo' => 1, 'bar' => 3]
```

### [`Hazaar\Util\Arr::grammaticalImplode()`](/api/class/Hazaar/Util/Arr.html)

Implode an array in a grammatically correct way:

```php
$items = ['One', 'Two', 'Three'];
echo Arr::grammaticalImplode($items); // One, Two and Three
```

---

## String Utilities ([`Hazaar\Util\Str`](/api/class/Hazaar/Util/Str.html))

Some of the most useful string helpers include:

### [`Str::fromBytes()`](/api/class/Hazaar/Util/Str.html)
Format a byte value as a human-readable string:
```php
use Hazaar\Util\Str;
echo Str::fromBytes(1048576); // 1MB
```

### [`Str::toBytes()`](/api/class/Hazaar/Util/Str.html)
Parse a human-readable byte string into a number:
```php
echo Str::toBytes('1MB'); // 1048576
```

### [`Str::guid()`](/api/class/Hazaar/Util/Str.html)
Generate a random GUID:
```php
echo Str::guid(); // e.g. 123e4567-e89b-12d3-a456-426614174000
```

---

## Number Utilities ([`Hazaar\Util\Number`](/api/class/Hazaar/Util/Number.html))

### [`Number::isEven()`](/api/class/Hazaar/Util/Number.html)
Check if a number is even:
```php
use Hazaar\Util\Number;

Number::isEven(4); // true
Number::isEven(5); // false
```

---

## Date/Time Utilities ([`Hazaar\Util\DateTime`](/api/class/Hazaar/Util/DateTime.html))

### [`DateTime::__construct()`](/api/class/Hazaar/Util/DateTime.html)
Create and format dates easily:
```php
use Hazaar\Util\DateTime;

echo new DateTime('next tuesday'); // e.g. 2025-07-01 00:00:00
```

### [`DateTime::setFormat()`](/api/class/Hazaar/Util/DateTime.html)
Set a custom output format:
```php
$date = new DateTime('2025-07-01');
$date->setFormat('Y-m-d');
echo $date; // 2025-07-01
```

Refer to the API documentation or source code in the `src/Util` directory for a full list and details on each utility class.