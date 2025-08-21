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