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