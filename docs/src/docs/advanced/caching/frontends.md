﻿# Frontends

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

As stated, frontends define the interface for how the application stores and retrieves data.

## Hazaar\Cache

The basic cache frontend is the core frontend that all other frontends are built upon and is used to store key/value pairs.  Keys are simply a unique text string to identify the cache record.  Values can be just about anything including strings, numbers, arrays and objects.  For an object to be stored it MUST be serializable.  For a class object to be serializable it must usually implement the `__sleep()` and `__wakeup()` methods.

### Options

Available options are:

|Name|Data Type|Default Value|Description|
|----|---------|-------------|-----------|
|lifetime|Integer|`7200`|The number of seconds after which cached data is considered stale and should be updated.  Once this timeout is reached, calls to `load()` will return `false`.|
|prefix|String|`null`|A prefix for all cache keys, if set to `null`, no cache key prefix will be used. The cache key prefix essentially creates a namespace in the cache, allowing multiple applications or websites to use a shared cache. Each application or website can use a different cache key prefix so specific cache keys can be used more than once.|
|serialize|Boolean|`false`|If a backend can not store objects (such as the file backend), then the data will automatically be serialized.  However, using this flag you can force the data to always be serialized and never stored as an object.|
|use_pragma|Boolean|`true`|If true, cache objects will check the pragma header and honour the no-cache parameter.  Typically this means that if the user does a hard refresh on a page (ie: using CTRL-F5) then this cache will be ignored.  If this is false, the pragma header will be ignored and the cache will always be used if available.|

### Methods

#### `load($key)`

|Parameter|Data Type|Description|
|---------|---------|-----------|
|$key|String|The unique identifier for the cache record that should be returned.|

#### `save($key, $data)`

|Parameter|Data Type|Description|
|---------|---------|-----------|
|$key|String|The unique identifier for the cache record.|
|$data|Mixed|The data that should be stored in the cache backend.|

```php
$cache = new Hazaar\Cache('basic', 'file');
if(($data = $cache->load('my_cache_key')) === false){
    $data = [];
    for($i=0;$i<1000;$i++){
        $data[$i] = uniqid();
    }
    $cache->save('my_cache_key', $data);
}
# Do something with $data here
```

## Hazaar\Cache\Func

The func frontend can cache the result of a function call as long as the result is an integer, string, boolean, array or serializable object.

### Options

Available options are:

|Name|Data Type|Default Value|Description|
|----|---------|-------------|-----------|
|cache_by_default|Boolean|`true`|If `true`, function calls will be cached by default.|
|cached_functions|Array|`null`|List of function names that will always be cached.|
|non_cached_functions|Array|`null`|List of function names that will never be cached.|

### Methods

#### `call($function, $args, [...])`

|Parameter|Data Type|Description|
|---------|---------|-----------|
|$function|Funcref|A function reference.  This is either a string to the function name (for global functions) or an array consisting of the object or class name as the first element, and the name of the function as the second element.  See PHP Callbacks for more examples.|
|$args|Mixed|The second and subsequent arguments are passed untouched to the function being called.|

Calling a global function:

```php
$cache = new Hazaar\Cache('func', 'file');
$result = $cache->call('myFunction', 'a string', 1234);
# Do something with result here
```

Calling a class method:

```php
$cache = new Hazaar\Cache('func', 'file');
$result = $cache->call([$obj, 'myMethod'], 'a string', 1234);
# Do something with result here
```

## Hazaar\Cache\Output

The output frontend is used to cache any output that is generated by the application.

### Options

There are no extra options available with this frontend.

### Methods

#### `start($key)`

This tells the cache object to start redirecting the output buffer into the cache object.

|Parameter|Data Type|Description|
|---------|---------|-----------|
|$key|String|The unique identifier for the cache record that should be returned.|

#### `stop()`

This tells the cache object to stop redirecting the output buffer into the cache object and store the buffered data in the cache backend.

```php
$cache = new Hazaar\Cache('output', 'file');
if(!$cache->start('my_cache_key')){
    echo "Hello world!";
    echo "This is going to be cached!";
}
echo "This string is never cached!";
```