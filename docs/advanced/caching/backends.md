# Backends

All the examples below use the basic frontend. Frontends do not depend on backends in any way so using other frontends is no different.

## Hazaar\Cache\Backend\Session

Example coming soon...

## Hazaar\Cache\Backend\File

Example coming soon...

## Hazaar\Cache\Backend\APC

Example coming soon...

## Hazaar\Cache\Backend\Redis

Example coming soon...

## Hazaar\Cache\Backend\Memcached

Example coming soon...

## Hazaar\Cache\Backend\Database

To prepare the database for use with a Hazaar cache backend you need to create the table that will store the cache data. At a minimum, the following SQL can be used to create a cache table:

```sql
CREATE TABLE IF NOT EXISTS hazaar_cache ( key TEXT PRIMARY KEY, value TEXT );
```

It is possible to add triggers and extra fields to this table if you wish but the cache backend will only use the key and value columns.

You can also replace the table name hazaar_cache with the name of the table you intend to use in the cache_db backend parameter.

## Hazaar\Cache\Backend\SQLite

Example coming soon...

## Hazaar\Cache\Backend\Chain

The chaining cache backend is slightly different to other backends in that it doesn't store anything itself and instead instantiates a group of other backends that have been requested. The chain backend is also selected a little differently due to each individual backend requiring it's own array of backend configuration parameters be passed to it. For this reason, the chain backend is not requested directly, and instead it is used when the `$backend` parameter of the Hazaar\Cache class is an array.

The format of the array is dynamic. If no backend options are needed, then the member value can be the name of the backend. To pass backend options the key is the backend name and the value is the array of configuration parameters to send to it.

```php
$config = array(
    'driver'   =>   'pgsql',
    'host'     =>   'localhost',
    'dbname'   =>   'hazaar_test',
    'user'     =>   'hazaar',
    'password' =>   'password'
);
$cache = new Hazaar\Cache('core', array(
    'database' => array('config' => $config ),
    'apc'
));
```

The above example shows how to instantiate the chain backend with two real backends, APC and Database. In this scenario the APC backend will be the primary backend as it is considered to be the faster of the two. Cache writes will occur on both backends but reads will occur on the APC backend. Only if the value does not exist will a read be performed on the Database backend and if that is successful, the APC backend will be updated with the found value.
