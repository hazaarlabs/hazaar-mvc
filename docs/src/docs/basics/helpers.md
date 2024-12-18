# Helper Functions

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

These functions are cool and helpful functions that do random things that I consider features that are missing from PHP.  They be extra array handler functions or some special shorthand functions that replace multiple lines of code (such as ake()).

## ake() - Array Key Exists

This is probably the most used and most useful helper function.  Normally when working with `array`s you need to check if a key exists first, then if so, grab the value and if they key doesn't exist, you need to use an alternative value.  Add to that, if they key exists but is an empty value?

Something like this, perhaps:

```php
$array = ['key' => 'value'];

if(array_key_exists('key', $array) && $array['key'] !== '')
    echo $array['key'];
else
    echo 'Missing!';
```    

What if you can handle all these situations in a single line of code?

```php
$array = ['key' => 'value'];

echo ake($array, 'key', 'Missing!', true);
```

For more information on options, see the ake() API documentation.