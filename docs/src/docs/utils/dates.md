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
